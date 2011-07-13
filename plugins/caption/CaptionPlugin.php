<?php
/**
 * Enable caption assets management for entry objects
 * @package plugins.caption
 */
class CaptionPlugin extends KalturaPlugin implements IKalturaServices, IKalturaPermissions, IKalturaEnumerator, IKalturaObjectLoader, IKalturaAdminConsoleEntryInvestigate, IKalturaConfigurator, IKalturaSchemaContributor, IKalturaMrssContributor
{
	const PLUGIN_NAME = 'caption';
	
	/* (non-PHPdoc)
	 * @see IKalturaPlugin::getPluginName()
	 */
	public static function getPluginName()
	{
		return self::PLUGIN_NAME;
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaPermissions::isAllowedPartner()
	 */
	public static function isAllowedPartner($partnerId)
	{
		$partner = PartnerPeer::retrieveByPK($partnerId);
		return $partner->getPluginEnabled(self::PLUGIN_NAME);
	}

	/* (non-PHPdoc)
	 * @see IKalturaServices::getServicesMap()
	 */
	public static function getServicesMap()
	{
		$map = array(
			'captionAsset' => 'CaptionAssetService',
			'captionParams' => 'CaptionParamsService',
		);
		return $map;
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaEnumerator::getEnums()
	 */
	public static function getEnums($baseEnumName = null)
	{
		if(is_null($baseEnumName))
			return array('CaptionAssetType');
	
		if($baseEnumName == 'assetType')
			return array('CaptionAssetType');
			
		return array();
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaObjectLoader::loadObject()
	 */
	public static function loadObject($baseClass, $enumValue, array $constructorArgs = null)
	{
		if($baseClass == 'KalturaAsset' && $enumValue == self::getAssetTypeCoreValue(CaptionAssetType::CAPTION))
			return new KalturaCaptionAsset();
	
		if($baseClass == 'KalturaAssetParams' && $enumValue == self::getAssetTypeCoreValue(CaptionAssetType::CAPTION))
			return new KalturaCaptionParams();
	
		return null;
	}

	/* (non-PHPdoc)
	 * @see IKalturaObjectLoader::getObjectClass()
	 */
	public static function getObjectClass($baseClass, $enumValue)
	{
		if($baseClass == 'asset' && $enumValue == self::getAssetTypeCoreValue(CaptionAssetType::CAPTION))
			return 'CaptionAsset';
	
		if($baseClass == 'assetParams' && $enumValue == self::getAssetTypeCoreValue(CaptionAssetType::CAPTION))
			return 'CaptionParams';
			
		return null;
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaAdminConsoleEntryInvestigate::getEntryInvestigatePlugins()
	 */
	public static function getEntryInvestigatePlugins()
	{
		return array(
			new Kaltura_View_Helper_EntryInvestigateCaptionAssets(),
		);
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaConfigurator::getConfig()
	 */
	public static function getConfig($configName)
	{
		if($configName == 'generator')
			return new Zend_Config_Ini(dirname(__FILE__) . '/config/generator.ini');
			
		return null;
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaSchemaContributor::isContributingToSchema()
	 */
	public static function isContributingToSchema($type)
	{
		$coreType = kPluginableEnumsManager::apiToCore('SchemaType', $type);
		return ($coreType == SchemaType::SYNDICATION);
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaSchemaContributor::contributeToSchema()
	 */
	public static function contributeToSchema($type, SimpleXMLElement $xsd)
	{
		$coreType = kPluginableEnumsManager::apiToCore('SchemaType', $type);
		if($coreType != SchemaType::SYNDICATION)
			return;
			
		$import = $xsd->addChild('import');
		$import->addAttribute('schemaLocation', 'http://' . kConf::get('cdn_host') . "/api_v3/service/schema/action/serve/type/$type/name/" . self::getPluginName());
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaSchemaContributor::contributeToSchema()
	 */
	public static function getPluginSchema($type)
	{
		$coreType = kPluginableEnumsManager::apiToCore('SchemaType', $type);
		if($coreType != SchemaType::SYNDICATION)
			return null;
			
		$xmlnsBase = "http://" . kConf::get('www_host') . "/$type";
		$xmlnsPlugin = "http://" . kConf::get('www_host') . "/$type/" . self::getPluginName();
		
		$xsd = '<?xml version="1.0" encoding="UTF-8"?>
			<xs:schema 
				xmlns:xs="http://www.w3.org/2001/XMLSchema"
				xmlns="' . $xmlnsPlugin . '" 
				xmlns:core="' . $xmlnsBase . '" 
				targetNamespace="' . $xmlnsPlugin . '"
			>
			
				<xs:complexType name="T_subTitle">
					<xs:sequence>
						<xs:element name="tags" minOccurs="1" maxOccurs="1" type="code:tags" />
						<xs:element ref="subtitle-extension" minOccurs="0" maxOccurs="unbounded" />
					</xs:sequence>
					
					<xs:attribute name="captionParamsId" type="xs:int" use="optional" />
					<xs:attribute name="captionParams" type="xs:string" use="optional" />
					<xs:attribute name="captionAssetId" type="xs:string" use="optional" />
					<xs:attribute name="isDefault" type="xs:boolean" use="optional" />
					<xs:attribute name="format" type="enums:KalturaCaptionType" use="optional" />
					<xs:attribute name="lang" type="enums:KalturaLanguage" use="optional" />
					<xs:attribute name="href" type="xs:string" use="optional" />
									
				</xs:complexType>
				
				<xs:element name="subtitle-extension" />
				<xs:element name="subTitle" type="T_subTitle" substitutionGroup="core:item-extension" />
			</xs:schema>
		';
		
		return new SimpleXMLElement($xsd);
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaMrssContributor::contributeToSchema()
	 */
	public function contribute(entry $entry, SimpleXMLElement $mrss)
	{
		$types = KalturaPluginManager::getExtendedTypes(assetPeer::OM_CLASS, CaptionPlugin::getAssetTypeCoreValue(CaptionAssetType::CAPTION));
		$captionAssets = assetPeer::retrieveByEntryId($entry->getId(), $types);
		
		foreach($captionAssets as $captionAsset)
			$this->contributeCaptionAssets($captionAssets, $mrss);
	}

	/**
	 * @param CaptionAsset $captionAsset
	 * @param SimpleXMLElement $mrss
	 * @return SimpleXMLElement
	 */
	public function contributeDistribution(CaptionAsset $captionAsset, SimpleXMLElement $mrss)
	{
		$subTitle = $mrss->addChild('subTitle');
		$subTitle->addAttribute('href', kMrssManager::getAssetUrl($captionAsset));
		$subTitle->addAttribute('captionAssetId', $captionAsset->getId());
		$subTitle->addAttribute('isDefault', $captionAsset->getDefault());
		$subTitle->addAttribute('format', $captionAsset->getContainerFormat());
		$subTitle->addAttribute('lang', $captionAsset->getLanguage());
		if($captionAsset->getFlavorParamsId())
			$subTitle->addAttribute('captionParamsId', $captionAsset->getFlavorParamsId());
			
		$tags = $subTitle->addChild('tags');
		foreach(explode(',', $captionAsset->getTags()) as $tag)
			$tags->addChild('tag', self::stringToSafeXml($tag));
	}
	
	/**
	 * @return int id of dynamic enum in the DB.
	 */
	public static function getAssetTypeCoreValue($valueName)
	{
		$value = self::getPluginName() . IKalturaEnumerator::PLUGIN_VALUE_DELIMITER . $valueName;
		return kPluginableEnumsManager::apiToCore('assetType', $value);
	}
	
	/**
	 * @return string external API value of dynamic enum.
	 */
	public static function getApiValue($valueName)
	{
		return self::getPluginName() . IKalturaEnumerator::PLUGIN_VALUE_DELIMITER . $valueName;
	}
}
