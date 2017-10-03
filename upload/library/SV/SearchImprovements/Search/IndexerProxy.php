<?php

class SV_SearchImprovements_Search_IndexerProxy extends XenForo_Search_Indexer
{
    protected $_proxiedIndexer = null;
    protected $_metadata       = [];

    public function __construct(XenForo_Search_Indexer $otherIndexer, array $metadata)
    {
        parent::__construct($otherIndexer->_sourceHandler);
        $this->_proxiedIndexer = $otherIndexer;
        $this->_metadata = $metadata;
    }

    public function setProxyMetaData(array $metadata)
    {
        $this->_metadata = XenForo_Application::mapMerge($this->_metadata, $metadata);
    }

    public function clearProxyMetaData()
    {
        $this->_metadata = [];
    }

    public function insertIntoIndex($contentType, $contentId, $title, $message, $itemDate, $userId, $discussionId = 0, array $metadata = [])
    {
        $metadata = XenForo_Application::mapMerge($metadata, $this->_metadata);
        $this->_proxiedIndexer->insertIntoIndex(
            $contentType, $contentId, $title, $message, $itemDate, $userId, $discussionId, $metadata
        );
    }
}
