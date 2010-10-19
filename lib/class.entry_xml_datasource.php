<?php

require_once(TOOLKIT . '/class.datasource.php');

Class EntryXMLDataSource extends Datasource{
	
	public $dsParamROOTELEMENT = 'entries';
	public $dsSource = NULL;
	
	public $dsParamORDER = 'desc';
	public $dsParamLIMIT = '99';
	public $dsParamREDIRECTONEMPTY = 'no';
	public $dsParamSORT = 'system:id';
	public $dsParamSTARTPAGE = '1';
	public $dsParamASSOCIATEDENTRYCOUNTS = 'no';
	
	public function __construct(&$parent, $env=NULL, $process_params=TRUE){
		parent::__construct($parent, $env, $process_params);
	}
	
	public function getSource(){
		return $this->dsSource;
	}
	
	public function grab(&$param_pool){

		$result = new XMLElement($this->dsParamROOTELEMENT);
		
		try{
			include(TOOLKIT . '/data-sources/datasource.section.php');
		}
		catch(Exception $e){
			$result->appendChild(new XMLElement('error', $e->getMessage()));
			return $result;
		}
		if($this->_force_empty_result) $result = $this->emptyXMLSet();

		return $result;		

	}
	
}