<?php

declare(strict_types=1);

namespace HeadlessChromium\Dom;

use HeadlessChromium\Communication\Message;
use HeadlessChromium\Communication\Response;
use HeadlessChromium\Exception\DomException;
use HeadlessChromium\Page;

class Node
{
    /**
     * @var Page
     */
    protected $page;

    /**
     * @var int
     */
    protected $nodeId;

    /**
     * @var bool
     */
    protected $isStale = false;

    public function __construct(Page $page, int $nodeId, bool $isRoot = false)
    {
        $this->page = $page;
        $this->nodeId = $nodeId;

        $page->getSession()->on('method:DOM.documentUpdated', function (...$event) use ($isRoot) {
            if ($isRoot === false) {
                $this->isStale = true;
            }
        });
    }

    public function getNodeId(): int
    {
        return $this->nodeId;
    }

    public function getAttributes(): NodeAttributes
    {
        $this->prepareForRequest();

        $message = new Message('DOM.getAttributes', [
            'nodeId' => $this->nodeId,
        ]);

        $response = $this->page->getSession()->sendMessageSync($message);

        $this->assertNotError($response);

        $attributes = $response->getResultData('attributes');

        return new NodeAttributes($attributes);
    }

    public function setAttributeValue(string $name, string $value): void
    {
        $this->prepareForRequest();

        $message = new Message('DOM.setAttributeValue', [
            'nodeId' => $this->nodeId,
            'name' => $name,
            'value' => $value,
        ]);

        $response = $this->page->getSession()->sendMessageSync($message);

        $this->assertNotError($response);
    }

    public function querySelector(string $selector): ?self
    {
        $this->prepareForRequest();

        $message = new Message('DOM.querySelector', [
            'nodeId' => $this->nodeId,
            'selector' => $selector,
        ]);

        $response = $this->page->getSession()->sendMessageSync($message);
        $this->assertNotError($response);

        $nodeId = $response->getResultData('nodeId');

        if (null !== $nodeId && 0 !== $nodeId) {
            return new self($this->page, $nodeId);
        }

        return null;
    }

    public function querySelectorAll(string $selector): array
    {
        $this->prepareForRequest();

        $message = new Message('DOM.querySelectorAll', [
            'nodeId' => $this->nodeId,
            'selector' => $selector,
        ]);

        $response = $this->page->getSession()->sendMessageSync($message);

        $this->assertNotError($response);

        $nodes = [];
        $nodeIds = $response->getResultData('nodeIds');
        foreach ($nodeIds as $nodeId) {
            $nodes[] = new self($this->page, $nodeId);
        }

        return $nodes;
    }

    public function focus(): void
    {
        $this->prepareForRequest();

        $message = new Message('DOM.focus', [
            'nodeId' => $this->nodeId,
        ]);
        $response = $this->page->getSession()->sendMessageSync($message);

        $this->assertNotError($response);
    }

    public function getAttribute(string $name): ?string
    {
        return $this->getAttributes()->get($name);
    }

    public function getPosition(): ?NodePosition
    {
        $this->prepareForRequest();

        $message = new Message('DOM.getBoxModel', [
            'nodeId' => $this->nodeId,
        ]);
        $response = $this->page->getSession()->sendMessageSync($message);

        $this->assertNotError($response);

        $points = $response->getResultData('model')['content'];

        if (null !== $points) {
            return new NodePosition($points);
        } else {
            return null;
        }
    }

    public function hasPosition(): bool
    {
        return null !== $this->getPosition();
    }

    public function getHTML(): string
    {
        $this->prepareForRequest();

        $message = new Message('DOM.getOuterHTML', [
            'nodeId' => $this->nodeId,
        ]);
        $response = $this->page->getSession()->sendMessageSync($message);

        $this->assertNotError($response);

        return $response->getResultData('outerHTML');
    }

    public function getText(): string
    {
        return \strip_tags($this->getHTML());
    }

    public function scrollIntoView(): void
    {
        $this->prepareForRequest();

        $message = new Message('DOM.scrollIntoViewIfNeeded', [
            'nodeId' => $this->nodeId,
        ]);
        $response = $this->page->getSession()->sendMessageSync($message);

        $this->assertNotError($response);
    }

    /**
     * @throws DomException
     */
    public function click(): void
    {
        $this->prepareForRequest();

        if (false === $this->hasPosition()) {
            throw new DomException('Failed to click element without position');
        }
        $this->scrollIntoView();
        $position = $this->getPosition();
        $this->page->mouse()
            ->move($position->getCenterX(), $position->getCenterY())
            ->click();
    }

    public function sendKeys(string $text): void
    {
        $this->prepareForRequest();

        $this->scrollIntoView();
        $this->focus();
        $this->page->keyboard()
            ->typeText($text);
    }

    public function sendFile(string $filePath): void
    {
        $this->sendFiles([$filePath]);
    }

    public function sendFiles(array $filePaths): void
    {
        $this->prepareForRequest();

        $message = new Message('DOM.setFileInputFiles', [
            'files' => $filePaths,
            'nodeId' => $this->nodeId,
        ]);
        $response = $this->page->getSession()->sendMessageSync($message);

        $this->assertNotError($response);
    }

    /**
     * @throws DomException
     */
    public function assertNotError(Response $response): void
    {
        if (!$response->isSuccessful()) {
            throw new DOMException($response->getErrorMessage());
        }
    }

    protected function prepareForRequest(): void
    {
        if ($this->isStale) {
            throw new StaleElementException();
        }

        $this->page->assertNotClosed();

        $this->page->getSession()->getConnection()->processAllEvents();
    }
}
