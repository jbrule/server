<?php
/**
 * Enable code cue point objects management on entry objects
 * @package plugins.codeCuePoint
 */
class CodeCuePointPlugin extends KalturaPlugin implements IKalturaPermissions, IKalturaEnumerator, IKalturaPending, IKalturaObjectLoader, IKalturaSchemaContributor
{
	const PLUGIN_NAME = 'codeCuePoint';
	const CUE_POINT_VERSION_MAJOR = 1;
	const CUE_POINT_VERSION_MINOR = 0;
	const CUE_POINT_VERSION_BUILD = 0;
	const CUE_POINT_NAME = 'cuePoint';
	
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
	 * @see IKalturaEnumerator::getEnums()
	 */
	public static function getEnums($baseEnumName = null)
	{
		if(is_null($baseEnumName))
			return array('CodeCuePointType');
	
		if($baseEnumName == 'CuePointType')
			return array('CodeCuePointType');
			
		return array();
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaPending::dependsOn()
	 */
	public static function dependsOn()
	{
		$cuePointVersion = new KalturaVersion(
			self::CUE_POINT_VERSION_MAJOR,
			self::CUE_POINT_VERSION_MINOR,
			self::CUE_POINT_VERSION_BUILD);
			
		$dependency = new KalturaDependency(self::CUE_POINT_NAME, $cuePointVersion);
		return array($dependency);
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaObjectLoader::loadObject()
	 */
	public static function loadObject($baseClass, $enumValue, array $constructorArgs = null)
	{
		if($baseClass == 'KalturaCuePoint' && $enumValue == self::getCuePointTypeCoreValue(CodeCuePointType::CODE))
			return new KalturaCodeCuePoint();
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaObjectLoader::getObjectClass()
	 */
	public static function getObjectClass($baseClass, $enumValue)
	{
		if($baseClass == 'CuePoint' && $enumValue == self::getCuePointTypeCoreValue(CodeCuePointType::CODE))
			return 'CodeCuePoint';
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaSchemaContributor::isContributingToSchema()
	 */
	public static function isContributingToSchema($type)
	{
		$coreType = kPluginableEnumsManager::apiToCore('SchemaType', $type);
		return (
			$coreType == SchemaType::SYNDICATION
			||
			$coreType == CuePointPlugin::getSchemaTypeCoreValue(CuePointSchemaType::SERVE_API)
			||
			$coreType == CuePointPlugin::getSchemaTypeCoreValue(CuePointSchemaType::INGEST_API)
		);
	}
	
	/* (non-PHPdoc)
	 * @see IKalturaSchemaContributor::contributeToSchema()
	 */
	public static function contributeToSchema($type, SimpleXMLElement $xsd)
	{
		$coreType = kPluginableEnumsManager::apiToCore('SchemaType', $type);
		if(
			$coreType != SchemaType::SYNDICATION
			&&
			$coreType != CuePointPlugin::getSchemaTypeCoreValue(CuePointSchemaType::SERVE_API)
			&&
			$coreType != CuePointPlugin::getSchemaTypeCoreValue(CuePointSchemaType::INGEST_API)
		)
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
		if(
			$coreType != SchemaType::SYNDICATION
			&&
			$coreType != CuePointPlugin::getSchemaTypeCoreValue(CuePointSchemaType::SERVE_API)
			&&
			$coreType != CuePointPlugin::getSchemaTypeCoreValue(CuePointSchemaType::INGEST_API)
		)
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
			
				<xs:complexType name="T_scene">
					<xs:complexContent>
						<xs:extension base="cuePoint:T_scene">
							<xs:sequence>
								<xs:element name="code" minOccurs="0" maxOccurs="1" type="xs:string" />
								<xs:element name="description" minOccurs="0" maxOccurs="1" type="xs:string" />
							</xs:sequence>
						</xs:extension>
					</xs:complexContent>
				</xs:complexType>
				
				<xs:element name="scene" type="T_scene" substitutionGroup="cuePoint:scene" />
			</xs:schema>
		';
		
		return new SimpleXMLElement($xsd);
	}
	
	/**
	 * @return int id of dynamic enum in the DB.
	 */
	public static function getCuePointTypeCoreValue($valueName)
	{
		$value = self::getPluginName() . IKalturaEnumerator::PLUGIN_VALUE_DELIMITER . $valueName;
		return kPluginableEnumsManager::apiToCore('CuePointType', $value);
	}
	
	/**
	 * @return string external API value of dynamic enum.
	 */
	public static function getApiValue($valueName)
	{
		return self::getPluginName() . IKalturaEnumerator::PLUGIN_VALUE_DELIMITER . $valueName;
	}
}
