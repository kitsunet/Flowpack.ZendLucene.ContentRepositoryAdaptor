<?php
namespace Flowpack\ZendLucene\ContentRepositoryAdaptor\Mapping;

/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch.ContentRepositoryAdaptor". *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use Flowpack\ZendLucene\Mapping\DataMapper;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeType;
use TYPO3\TYPO3CR\Domain\Service\NodeTypeManager;

/**
 * Builds the mapping information for TYPO3CR Node Types in Zend Lucene
 *
 * @Flow\Scope("singleton")
 */
class NodeTypeMappingBuilder {

	/**
	 * The default configuration for a given property type in NodeTypes.yaml, if no explicit elasticSearch section defined there.
	 *
	 * @var array
	 */
	protected $defaultConfigurationPerType;

	/**
	 * @Flow\Inject
	 * @var NodeTypeManager
	 */
	protected $nodeTypeManager;

	/**
	 * @var \TYPO3\Flow\Error\Result
	 */
	protected $lastMappingErrors;

	/**
	 * @Flow\Inject
	 * @var DataMapper
	 */
	protected $dataMapper;

	/**
	 * @param array $settings
	 */
	public function injectSettings(array $settings) {
		$this->defaultConfigurationPerType = $settings['defaultConfigurationPerType'];
	}

	/**
	 * Build a mapping configuration for all node types and sets it to the DataMapper
	 *
	 * @return array
	 */
	public function buildMappingInformation() {
		$completeMappingInformation = array();
		/** @var NodeType $nodeType */
		foreach ($this->nodeTypeManager->getNodeTypes() as $nodeTypeName => $nodeType) {
			$dataMapForType = array();
			if ($nodeTypeName === 'unstructured' || $nodeType->isAbstract()) {
				continue;
			}

			foreach ($nodeType->getProperties() as $propertyName => $propertyConfiguration) {
				if (isset($propertyConfiguration['zendLucene']['mapping'])) {
					$dataMapForType[$propertyName] = $propertyConfiguration['zendLucene']['mapping'];
				} elseif (isset($propertyConfiguration['type']) && isset($this->defaultConfigurationPerType[$propertyConfiguration['type']]['mapping'])) {
					$dataMapForType[$propertyName] = $this->defaultConfigurationPerType[$propertyConfiguration['type']]['mapping'];
				}
			}

			$this->dataMapper->addMappingConfiguration($nodeType->getName(), $dataMapForType);
			$completeMappingInformation[$nodeType->getName()] = $dataMapForType;
		}

		return $completeMappingInformation;
	}
}

