<?php

class OsBundleMetaModel extends OsMetaModel{
	function __construct($object_id = false){
		$this->table_name = LATEPOINT_TABLE_BUNDLE_META;
		parent::__construct($object_id);
	}
}