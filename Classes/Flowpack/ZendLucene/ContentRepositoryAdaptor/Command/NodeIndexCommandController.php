<?php
namespace Flowpack\ZendLucene\ContentRepositoryAdaptor\Command;

/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch.ContentRepositoryAdaptor". *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use Flowpack\ZendLucene\ContentRepositoryAdaptor\Mapping\NodeTypeMappingBuilder;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cli\CommandController;

/**
 * Provides CLI features for index handling
 *
 * @Flow\Scope("singleton")
 */
class NodeIndexCommandController extends CommandController {

	/**
	 * @Flow\Inject
	 * @var \Flowpack\ZendLucene\ContentRepositoryAdaptor\Indexer\NodeIndexer
	 */
	protected $nodeIndexer;

	/**
	 * @Flow\Inject
	 * @var \Flowpack\ZendLucene\Index\Client
	 */
	protected $indexClient;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository
	 */
	protected $nodeDataRepository;

	/**
	 * @Flow\Inject
	 * @var NodeTypeMappingBuilder
	 */
	protected $nodeTypeMappingBuilder;

	/**
	 * Show the mapping which would be sent to Zend Lucene
	 *
	 * @return void
	 */
	public function showMappingCommand() {
		$nodeTypeMappingCollection = $this->nodeTypeMappingBuilder->buildMappingInformation();
		foreach ($nodeTypeMappingCollection as $name => $mapping) {
			$this->outputLine($name);
			$this->output(\Symfony\Component\Yaml\Yaml::dump($mapping, 5, 2));
			$this->outputLine();
		}
		$this->outputLine('------------');
	}

	/**
	 * Index all nodes.
	 *
	 * This command (re-)indexes all nodes contained in the content repository and sets the schema beforehand.
	 *
	 * @param integer $limit Amount of nodes to index at maximum
	 * @return void
	 */
	public function buildCommand($limit = NULL) {
		$this->nodeTypeMappingBuilder->buildMappingInformation();
		$this->nodeIndexer->setIndexClient($this->indexClient);
		$count = 0;
		foreach ($this->nodeDataRepository->findAll() as $nodeData) {
			if ($limit !== NULL && $count > $limit) {
				break;
			}
			$document = $this->nodeIndexer->indexNode($nodeData);
			if ($document !== NULL) {
				$this->outputLine('Indexed:' . implode(',', $document->getFieldNames()));
			}
			$count ++;
		}

		$this->indexClient->getZendIndex()->commit();
		$this->outputLine('Done. (indexed ' . $count . ' nodes)');
	}

	/**
	 * Optimize index.
	 *
	 * @return void
	 */
	public function optimizeCommand() {
		$this->indexClient->getZendIndex()->optimize();
		$this->outputLine('Done optimizing');
	}

	/**
	 * Utility to check the content of the index.
	 *
	 * @param string $queryString
	 * @param string $orderField
	 */
	public function findCommand($queryString, $orderField = NULL) {
		$this->outputLine('Index has ' . $this->indexClient->getZendIndex()->numDocs() . ' documents.');
		$start = microtime(TRUE);
		if ($orderField !== NULL) {
			$hits = $this->indexClient->find($queryString, $orderField);
		} else {
			$hits = $this->indexClient->find($queryString);
		}
		$this->outputLine('Total found: ' . count($hits));
		foreach ($hits as $hit) {
			$this->outputLine('Found: ' . var_export($hit->getDocument()->getFieldValue('__typeAndSupertypes'), TRUE));
		}
		$end = microtime(TRUE);
		$this->outputLine($end - $start);
	}
}