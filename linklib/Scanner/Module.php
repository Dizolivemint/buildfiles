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
 * Scanner class for Joomla! modules
 */
class Module extends AbstractScanner
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
		$this->manifestExtensionType = 'module';

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
			throw new RuntimeException("Cannot get XML manifest for module in {$this->extensionRoot}");
		}

		// Intiialize the result
		$result                = new ScanResult();
		$result->extensionType = 'module';

		// Get the extension name
		$files  = $xmlDoc->getElementsByTagName('files')->item(0)->childNodes;
		$module = null;

		/** @var \DOMElement $file */
		foreach ($files as $file)
		{
			if ($file->hasAttributes())
			{
				$module = $file->getAttribute('module');

				break;
			}
		}

		if (is_null($module))
		{
			throw new RuntimeException("Cannot find the module name in the XML manifest for {$this->extensionRoot}");
		}

		$result->extension = $module;

		// Is this is a site or administrator module?
		$isSite = $xmlDoc->documentElement->getAttribute('client') == 'site';

		// Get the main folder to link
		if ($isSite)
		{
			$result->siteFolder = $this->extensionRoot;
		}
		else
		{
			$result->adminFolder = $this->extensionRoot;
		}

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

			if ($isSite)
			{
				$result->siteLangFiles = $languageFiles;
				$result->siteLangPath  = $languageRoot;

				continue;
			}

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

		$source = $scan->siteFolder;
		$basePath = $this->siteRoot . '/';

		if (!empty($scan->adminFolder))
		{
			$basePath .= 'administrator/';
			$source = $scan->adminFolder;
		}

		$basePath .= 'modules/' . $scan->extension;

		// Frontend and backend directories
		$dirs = [
			$source => $basePath
		];

		$result->dirs = array_merge($result->dirs, $dirs);

		return $result;
	}

}