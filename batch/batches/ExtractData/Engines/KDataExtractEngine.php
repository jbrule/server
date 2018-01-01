<?php
/**
 *
 * @package Scheduler
 * @subpackage ExtractData.engines
 */
abstract class KDataExtractEngine
{
    CONST START_TIME_FIELD = 'startTime';
    CONST DATA_FIELD = 'data';
    /**
     * Will return the proper engine depending on the type (KalturaDataExtractEngineType)
     *
     * @param int $type
     * @return KDataExtractEngine
     */
    public static function getInstance($type)
    {
        switch ($type)
        {
            case KalturaConversionEngineType::MUSIC_RECOGNIZER:
                return new KDataExtractMusicEngine();
            case KalturaConversionEngineType::CHAPTER_LINKER:
                return null;
            default:
                return null;
        }
    }

    abstract public function getSubType();

    /**
     * @param KalturaFileContainer $fileContainer
     *  return array of keys: startTime and data
     * @return array
     */
    abstract public function extractData ( KalturaFileContainer $fileContainer );
    
}
