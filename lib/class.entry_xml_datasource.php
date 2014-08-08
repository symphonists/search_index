<?php

require_once(TOOLKIT . '/class.datasource.php');

Class EntryXMLDataSource extends SectionDatasource{
	
	public $dsParamROOTELEMENT = 'entries';
	public $dsSource = NULL;
	
	public $dsParamORDER = 'desc';
	public $dsParamLIMIT = '99';
	public $dsParamREDIRECTONEMPTY = 'no';
	public $dsParamSORT = 'system:id';
	public $dsParamSTARTPAGE = '1';
	public $dsParamASSOCIATEDENTRYCOUNTS = 'no';
	
	public function __construct($env=NULL, $process_params=true){
		parent::__construct($env, $process_params);
	}
	
	public function getSource(){
		return $this->dsSource;
	}
	
	public function execute(array &$param_pool = null) {
		$result = new XMLElement($this->dsParamROOTELEMENT);

		try{
			$result = parent::execute($param_pool);
		}
		catch(FrontendPageNotFoundException $e){
			// Work around. This ensures the 404 page is displayed and
			// is not picked up by the default catch() statement below
			FrontendPageNotFoundExceptionHandler::render($e);
		}
		catch(Exception $e){
			$result->appendChild(new XMLElement('error', $e->getMessage() . ' on ' . $e->getLine() . ' of file ' . $e->getFile()));
			return $result;
		}

		if($this->_force_empty_result) $result = $this->emptyXMLSet();

		return $result;
	}
	
}