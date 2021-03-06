<?php
namespace Flowpack\ZendLucene\ContentRepositoryAdaptor\Indexer;

/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch.ContentRepositoryAdaptor". *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeData;
use TYPO3\TYPO3CR\Domain\Service\NodeTypeManager;


/**
 * Indexer for Content Repository Nodes. Triggered from the NodeIndexingManager.
 *
 * @Flow\Scope("singleton")
 */
class NodeIndexer {

	/**
	 * @var \Flowpack\ZendLucene\Index\Client
	 */
	protected $indexClient;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Persistence\PersistenceManagerInterface
	 */
	protected $persistenceManager;

	/**
	 * @Flow\Inject
	 * @var NodeTypeManager
	 */
	protected $nodeTypeManager;

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * the default context variables available inside Eel
	 *
	 * @var array
	 */
	protected $defaultContextVariables;

	/**
	 * @var \TYPO3\Eel\CompilingEvaluator
	 * @Flow\Inject
	 */
	protected $eelEvaluator;

	/**
	 * The default configuration for a given property type in NodeTypes.yaml, if no explicit elasticSearch section defined there.
	 *
	 * @var array
	 */
	protected $defaultConfigurationPerType;

	/**
	 * @Flow\Inject
	 * @var \Flowpack\ZendLucene\Mapping\DataMapper
	 */
	protected $dataMapper;


	/**
	 * @param array $settings
	 */
	public function injectSettings(array $settings) {
		$this->defaultConfigurationPerType = $settings['defaultConfigurationPerType'];
		$this->settings = $settings;
	}

	/**
	 * @param \Flowpack\ZendLucene\Index\Client $indexClient
	 */
	public function setIndexClient($indexClient) {
		$this->indexClient = $indexClient;
	}

	/**
	 * @return \Flowpack\ZendLucene\Index\Client
	 */
	public function getIndexClient() {
		return $this->indexClient;
	}

	/**
	 * index this node, and add it to the current bulk request.
	 *
	 * @param NodeData $nodeData
	 * @throws \Exception
	 * @return \ZendSearch\Lucene\Document
	 */
	public function indexNode(NodeData $nodeData) {
		$persistenceObjectIdentifier = $this->persistenceManager->getIdentifierByObject($nodeData);
		$nodeType = $nodeData->getNodeType();

		$hits = $this->indexClient->find('__persistenceObjectIdentifier:"' . $persistenceObjectIdentifier . '"');
		foreach ($hits as $hit) {
			$this->indexClient->removeDocument($hit->getDocument());
		}

		if ($nodeData->isRemoved()) {
			return;
		}

		$nodePropertiesToBeStoredInIndex = array();
		foreach ($nodeType->getProperties() as $propertyName => $propertyConfiguration) {
			if (isset($propertyConfiguration['zendLucene']['indexing'])) {
				if ($propertyConfiguration['zendLucene']['indexing'] !== '') {
					$valueToStore = $this->evaluateEelExpression($propertyConfiguration['zendLucene']['indexing'], $nodeData, $propertyName, ($nodeData->hasProperty($propertyName) ? $nodeData->getProperty($propertyName) : NULL), $persistenceObjectIdentifier);

					$nodePropertiesToBeStoredInIndex[$propertyName] = $valueToStore;
				}
			} elseif (isset($propertyConfiguration['type']) && isset($this->defaultConfigurationPerType[$propertyConfiguration['type']]['indexing'])) {
				if ($this->defaultConfigurationPerType[$propertyConfiguration['type']]['indexing'] !== '') {
					$valueToStore = $this->evaluateEelExpression($this->defaultConfigurationPerType[$propertyConfiguration['type']]['indexing'], $nodeData, $propertyName, ($nodeData->hasProperty($propertyName) ? $nodeData->getProperty($propertyName) : NULL), $persistenceObjectIdentifier);
					$nodePropertiesToBeStoredInIndex[$propertyName] = $valueToStore;
				}
			}
		}


		$document = new \ZendSearch\Lucene\Document();
		$this->dataMapper->mapToDocument($document, $nodePropertiesToBeStoredInIndex, $nodeType->getName());

		$this->indexClient->addDocument($document);

		return $document;
	}

	/**
	 * Evaluate an Eel expression.
	 *
	 * TODO: REFACTOR TO Eel package (as this is copy/pasted from TypoScript Runtime)
	 *
	 * @param string $expression The Eel expression to evaluate
	 * @param NodeData $node
	 * @param string $propertyName
	 * @param mixed $value
	 * @param string $persistenceObjectIdentifier
	 * @return mixed The result of the evaluated Eel expression
	 * @throws Exception
	 */
	protected function evaluateEelExpression($expression, NodeData $node, $propertyName, $value, $persistenceObjectIdentifier) {
		$matches = NULL;
		if (preg_match(\TYPO3\Eel\Package::EelExpressionRecognizer, $expression, $matches)) {
			$contextVariables = array_merge($this->getDefaultContextVariables(), array(
				'node' => $node,
				'propertyName' => $propertyName,
				'value' => $value,
				'persistenceObjectIdentifier' => $persistenceObjectIdentifier
			));

			$context = new \TYPO3\Eel\Context($contextVariables);

			$value = $this->eelEvaluator->evaluate($matches['exp'], $context);

			return $value;
		} else {
			throw new Exception('The Indexing Eel expression "' . $expression . '" used to index property "' . $propertyName . '" of "' . $node->getNodeType()->getName() . '" was not a valid Eel expression. Perhaps you forgot to wrap it in ${...}?', 1383635796);
		}
	}

	/**
	 * Get variables from configuration that should be set in the context by default.
	 * For example Eel helpers are made available by this.
	 *
	 * TODO: REFACTOR TO Eel package (as this is copy/pasted from TypoScript Runtime
	 *
	 * @return array Array with default context variable objects.
	 */
	protected function getDefaultContextVariables() {
		if ($this->defaultContextVariables === NULL) {
			$this->defaultContextVariables = array();
			if (isset($this->settings['defaultContext']) && is_array($this->settings['defaultContext'])) {
				foreach ($this->settings['defaultContext'] as $variableName => $objectType) {
					$currentPathBase = &$this->defaultContextVariables;
					$variablePathNames = explode('.', $variableName);
					foreach ($variablePathNames as $pathName) {
						if (!isset($currentPathBase[$pathName])) {
							$currentPathBase[$pathName] = array();
						}
						$currentPathBase = &$currentPathBase[$pathName];
					}
					$currentPathBase = new $objectType();
				}
			}
		}

		return $this->defaultContextVariables;
	}

}