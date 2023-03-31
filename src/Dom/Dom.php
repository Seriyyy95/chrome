<?php

declare(strict_types=1);

namespace HeadlessChromium\Dom;

use HeadlessChromium\Communication\Message;
use HeadlessChromium\Page;

class Dom extends Node
{
    public function __construct(Page $page)
    {
        $rootNodeId = $this->getRootNodeId($page);

        parent::__construct($page, $rootNodeId, true);

        $page->getSession()->on('method:DOM.documentUpdated', function (...$event) {
            $this->nodeId = $this->getRootNodeId($this->page);
        });
    }

    private function getRootNodeId(Page $page): ?int
    {
        $message = new Message('DOM.getDocument');
        $stream = $page->getSession()->sendMessage($message);
        $response = $stream->waitForResponse(1000);

        return $response->getResultData('root')['nodeId'];
    }

    /**
     * @return Node[]
     */
    public function search(string $selector): array
    {
        $this->prepareForRequest();

        $message = new Message('DOM.performSearch', [
            'query' => $selector,
        ]);
        $response = $this->page->getSession()->sendMessageSync($message);

        $this->assertNotError($response);

        $searchId = $response->getResultData('searchId');
        $count = $response->getResultData('resultCount');

        if (0 === $count) {
            return [];
        }
        $message = new Message('DOM.getSearchResults', [
            'searchId' => $searchId,
            'fromIndex' => 0,
            'toIndex' => $count,
        ]);

        $response = $this->page->getSession()->sendMessageSync($message);

        $this->assertNotError($response);

        $nodes = [];
        $nodeIds = $response->getResultData('nodeIds');
        foreach ($nodeIds as $nodeId) {
            $nodes[] = new Node($this->page, $nodeId);
        }

        return $nodes;
    }
}
