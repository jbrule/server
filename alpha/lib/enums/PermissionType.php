<?php
/**
 * @package lib.model
 * @subpackage enum
 */ 
interface PermissionType extends BaseEnum
{
	const API_ACCESS       = '1';
	const SPECIAL_FEATURE  = '2';
	const PLUGIN           = '3';
	const PARTNER_GROUP    = '4';
	const EXTERNAL         = '99';
}