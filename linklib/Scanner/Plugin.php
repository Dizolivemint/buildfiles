<?php
/**
 * Akeeba Build Tools
 *
 * @package        buildfiles
 * @license        GPL v3
 * @copyright      2010-2017 Akeeba Ltd
 */

namespace Akeeba\LinkLibrary\Scanner;

use Akeeba\LinkLibrary\MapResult;
use Akeeba\LinkLibrary\ScanResult;
use RuntimeException;

/**
 * Scanner class for Joomla! plugins
 */
class Plugin extends AbstractScanner
{
	/**
	 * Constructor.
	 *
	 * The languageRoot is optional and applies only if the languages are stored in a directory other than the one
	 * specified in the extension's XML file.
	 *
	 * @param   string  $extensionRoot  The absolute path to the extension's root folder
	 * @param   string  $languageRoot   The absolute path to the extension's language folder (optional)
	 */
	public function __construct($extensionRoot, $languageRoot = null)
	{
		$this->manifestExtensionType = 'plugin';

		parent::__construct($extensionRoot, $languageRoot);
	}

	/**
	 * Scans the extension for files and folders to link
	 *
	 * @return  ScanResult
	 */
	public function scan()
	{
		// Get the XML manifest
		$xmlDoc = $this->getXMLManifest();

		if (empty($xmlDoc))
		{
			throw new RuntimeException("Cannot get XML manifest for plugin in {$this->extensionRoot}");
		}

		// Intiialize the result
		$result                = new ScanResult();
		$result->extensionType = 'plugin';

		// Get the extension name
		$files  = $xmlDoc->getElementsByTagName('files')->item(0)->childNodes;
		$plugin = null;

		/** @var \DOMElement $file */
		foreach ($files as $file)
		{
			if ($file->hasAttributes())
			{
				$plugin = $file->getAttribute('plugin');

				break;
			}
		}

		if (is_null($plugin))
		{
			throw new RuntimeException("Cannot find the plugin name in the XML manifest for {$this->extensionRoot}");
		}

		$result->extension = $plugin;

		// Is this is a site or administrator module?
		$result->pluginFolder = $xmlDoc->documentElement->getAttribute('group');

		// Get the main folder to link
		$result->siteFolder = $this->extensionRoot;

		// Get the media folder
		$result->mediaFolder      = null;
		$result->mediaDestination = null;
		$allMediaTags             = $xmlDoc->getElementsByTagName('media');

		if ($allMediaTags->length >= 1)
		{
			$result->mediaFolder      = $this->extensionRoot . '/' . (string) $allMediaTags->item(0)
			                                                                               ->getAttribute('folder');
			$result->mediaDestination = $allMediaTags->item(0)->getAttribute('destination');
		}

		// Get the <languages> tag
		$xpath = new \DOMXPath($xmlDoc);
		$languagesNodes = $xpath->query('/extension/languages');

		foreach ($languagesNodes as $node)
		{
			list($languageRoot, $languageFiles) = $this->scanLanguageNode($node);

			if (empty($languageFiles))
			{
				continue;
			}

			// Plugin language files always go to the backend language folder
			$result->adminLangFiles = $languageFiles;
			$result->adminLangPath  = $languageRoot;
		}

		// TODO Scan language files in a separate root, if one is specified

		return $result;
	}

	/**
	 * Parses the last scan and generates a link map
	 *
	 * @return  MapResult
	 */
	public function map()
	{
		$scan = $this->getScanResults();
		$result = parent::map();

		$basePath = $this->siteRoot . '/plugins/' . $scan->pluginFolder . '/' . $scan->extension;

		// Frontend and backend directories
		$dirs = [
			$scan->siteFolder => $basePath
		];

		$result->dirs = array_merge($result->dirs, $dirs);

		return $result;
	}

}