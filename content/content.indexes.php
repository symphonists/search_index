<?php
	
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	
	require_once(EXTENSIONS . '/search_index/lib/class.search_index.php');
	require_once(EXTENSIONS . '/search_index/lib/class.entry_xml_datasource.php');
	require_once(EXTENSIONS . '/search_index/lib/class.reindex_datasource.php');
	
	class contentExtensionSearch_IndexIndexes extends AdministrationPage {
		protected $_errors = array();
		
		public function __construct(&$parent){
			parent::__construct($parent);
			
			$this->_uri = URL . '/symphony/extension/search_index';
			
			$sectionManager = new SectionManager(Administration::instance());			
			$this->_sections = $sectionManager->fetch(NULL, 'ASC', 'name');
			
			$this->_indexes = SearchIndex::getIndexes();
			
			$this->_section = NULL;
			$this->_index = NULL;
			$this->_weightings = array(
				__('Highest'),
				__('High'),
				__('Medium (none)'),
				__('Low'),
				__('Lowest')
			);
		}
		
		public function build($context) {
			$this->__prepareEdit($context);		
			parent::build($context);
		}
		
		private function __setContext($section_id) {
			$this->_index = $this->_indexes[$section_id];
			if (is_array($this->_sections)) {
				foreach($this->_sections as $s) {
					if ($s->get('id') == $section_id) $this->_section = $s;
				}
			}
		}
				
		public function __prepareEdit($context) {
			$this->__setContext($context[1]);			
			
			if (!is_array($this->_index['fields'])) $this->_index['fields'] = array($this->_index['fields']);
			if (!is_array($this->_index['filters'])) $this->_index['filters'] = array($this->_index['filters']);
		}
		
		public function __actionIndex() {
			$checked = @array_keys($_POST['items']);
			
			if (is_array($checked) and !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						foreach ($checked as $section_id) {
							
							$this->__setContext($section_id);
							
							// ignore if no index is set for a selected section
							if (is_null($this->_index)) continue;
							
							SearchIndex::deleteIndexBySection($section_id);							
							unset($this->_indexes[$section_id]);
							SearchIndex::saveIndexes($this->_indexes);
							
						}						
						redirect("{$this->_uri}/indexes/");
						break;
						
					case 're-index':
						foreach ($checked as $section_id) {
							#SearchIndex::deleteIndexBySection($section_id);
						}
						redirect("{$this->_uri}/indexes/?section=" . join(',', $checked));
						break;
				}
			}
		}
		
		public function __actionEdit() {
			
			$fields = $_POST['fields'];
			
			$is_new = !isset($this->_indexes[$this->_section->get('id')]);			
			
			$this->_indexes[$this->_section->get('id')]['fields'] = $fields['included_elements'];
			$this->_indexes[$this->_section->get('id')]['weighting'] = $fields['weighting'];
			
			if (!is_array($fields['filter'])) $fields['filter'] = array($fields['filter']);
			
			$filters = array();
			foreach($fields['filter'] as $filter) {
				if (is_null($filter)) continue;
				$filters[key($filter)] = $filter[key($filter)];
			}
			$this->_indexes[$this->_section->get('id')]['filters'] = $filters;
			
			SearchIndex::saveIndexes($this->_indexes);
			
			redirect("{$this->_uri}/indexes/");
		}
		
		public function __viewEdit() {
			$this->addStylesheetToHead(URL . '/extensions/search_index/assets/search_index.css', 'screen', 100);
			$this->addScriptToHead(URL . '/extensions/search_index/assets/search_index.js', 101);
			
			$this->setPageType('form');
			$this->setTitle(__('Symphony') . ' &ndash; ' . __('Search Indexes') . ' &ndash; ' . $this->_section->get('name'));
			$this->appendSubheading(__('Search Index') . " &rsaquo; <a href=\"{$this->_uri}/indexes/\">" . __('Indexes') . "</a> <span class='meta'>" . $this->_section->get('name') . "</span>");
			
			$fields = array('fields' => $this->_section->fetchFields(), 'section' => $this->_section);
			
			//var_dump($this->_index);die;
			
			$fields_options = array();
			foreach($fields['fields'] as $f) {				
				$fields_options[] = array(
					$f->get('element_name'),
					in_array($f->get('element_name'), $this->_index['fields']),
					$f->get('label')
				);
			}
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Included Fields')));
			$p = new XMLElement('p', __('Only selected fields will be indexed.'));
			$p->setAttribute('class', 'help');
			$fieldset->appendChild($p);
			
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			
			$label = Widget::Label(__('Included Fields'));
			$label->appendChild(Widget::Select(
				'fields[included_elements][]',
				$fields_options,
				array('multiple'=>'multiple')
			));
			$group->appendChild($label);
			
			$weighting_options = array();
			if ($this->_index['weighting'] == NULL) $this->_index['weighting'] = 2;
			foreach($this->_weightings as $i => $w) {
				$weighting_options[] = array(
					$i,
					($i == $this->_index['weighting']),
					$w
				);
			}
			
			$label = Widget::Label(__('Weighting'));
			$label->appendChild(Widget::Select(
				'fields[weighting]',
				$weighting_options
			));
			$group->appendChild($label);
			
			$fieldset->appendChild($group);
			$this->Form->appendChild($fieldset);
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings contextual ' . __('sections') . ' ' . __('authors') . ' ' . __('navigation') . ' ' . __('Sections') . ' ' . __('System'));
			$fieldset->appendChild(new XMLElement('legend', __('Index Filters')));
			$p = new XMLElement('p', __('Only entries that pass these filters will be indexed.'));
			$p->setAttribute('class', 'help');
			$fieldset->appendChild($p);
				
			$div = new XMLElement('div');
			$div->setAttribute('class', 'contextual ' . $fields['section']->get('id'));
			$h3 = new XMLElement('p', __('Filter %s by', array($fields['section']->get('name'))), array('class' => 'label'));
			$h3->setAttribute('class', 'label');
			$div->appendChild($h3);
			
			$ol = new XMLElement('ol');
			$ol->setAttribute('class', 'filters-duplicator');
			
			if(isset($this->_index['filters']['id'])){
				$li = new XMLElement('li');
				$li->setAttribute('class', 'unique');
				$li->appendChild(new XMLElement('h4', __('System ID')));
				$label = Widget::Label(__('Value'));
				$label->appendChild(Widget::Input('fields[filter]['.$fields['section']->get('id').'][id]', General::sanitize($this->_index['filters']['id'])));
				$li->appendChild($label);
				$ol->appendChild($li);				
			}
			
			$li = new XMLElement('li');
			$li->setAttribute('class', 'unique template');
			$li->appendChild(new XMLElement('h4', __('System ID')));
			$label = Widget::Label(__('Value'));
			$label->appendChild(Widget::Input('fields[filter]['.$fields['section']->get('id').'][id]'));
			$li->appendChild($label);
			$ol->appendChild($li);
			
			if(is_array($fields['fields']) && !empty($fields['fields'])){
				foreach($fields['fields'] as $input){
				
					if(!$input->canFilter()) continue;
							
					if(isset($this->_index['filters'][$input->get('id')])){
						$wrapper = new XMLElement('li');
						$wrapper->setAttribute('class', 'unique');
						$input->displayDatasourceFilterPanel($wrapper, $this->_index['filters'][$input->get('id')], $this->_errors[$input->get('id')], $fields['section']->get('id'));
						$ol->appendChild($wrapper);					
					}
			
					$wrapper = new XMLElement('li');
					$wrapper->setAttribute('class', 'unique template');
					$input->displayDatasourceFilterPanel($wrapper, NULL, NULL, $fields['section']->get('id'));
					$ol->appendChild($wrapper);

				}
			}
			
			$div->appendChild($ol);
			$fieldset->appendChild($div);
			$this->Form->appendChild($fieldset);
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(
				Widget::Input('action[save]',
					__('Save Changes'),
					'submit', array(
						'accesskey' => 's'
					)
				)
			);
						
			$this->Form->appendChild($div);
		}
		
		public function __viewIndex() {
			$this->setPageType('table');
			$this->setTitle(__('Symphony') . ' &ndash; ' . __('Search Indexes'));
			
			$this->appendSubheading(__('Search Index') . " &rsaquo; " . __('Indexes'));
			$this->Form->appendChild(new XMLElement('p', __('Configure how each of your sections are indexed. Choose which field text values to index, which entries to index, and the weighting of the section in search results.'), array('class' => 'intro')));
			
			$this->addElementToHead(new XMLElement(
				'script',
				"Symphony.Context.add('search_index', " . json_encode(Symphony::Configuration()->get('search_index')) . ")",
				array('type' => 'text/javascript')
			), 99);
			
			$this->addStylesheetToHead(URL . '/extensions/search_index/assets/search_index.css', 'screen', 100);
			$this->addScriptToHead(URL . '/extensions/search_index/assets/search_index.js', 101);
			
			$tableHead = array();
			$tableBody = array();
			
			$tableHead[] = array(__('Section'), 'col');
			$tableHead[] = array(__('Fields'), 'col');
			$tableHead[] = array(__('Weighting'), 'col');
			$tableHead[] = array(__('Index Size'), 'col');
			
			if (!is_array($this->_sections) or empty($this->_sections)) {
				$tableBody = array(
					Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', null, count($tableHead))))
				);
			}
			
			else {
				
				$re_index = explode(',', $_GET['section']);
				
				foreach ($this->_sections as $section) {
					
					$index = NULL;
					if(isset($this->_indexes[$section->get('id')])) {
						$index = $this->_indexes[$section->get('id')];
					}
					
					$col_name = Widget::TableData(
						Widget::Anchor(
							$section->get('name'),
							"{$this->_uri}/indexes/edit/{$section->get('id')}/"
						)
					);
					
					if ($index) {
						$col_name->appendChild(Widget::Input("items[{$section->get('id')}]", null, 'checkbox'));
					}
					
					if ($index && isset($index['fields']) && count($index['fields'] > 0)) {
						$section_fields = $section->fetchFields();
						$fields = $this->_indexes[$section->get('id')]['fields'];
						$fields_list = '';
						foreach($section_fields as $section_field) {
							if (in_array($section_field->get('element_name'), array_values($fields))) {
								$fields_list .= $section_field->get('label') . ', ';
							}
						}
						$fields_list = trim($fields_list, ', ');
						$col_fields = Widget::TableData($fields_list);
					} else {
						$col_fields = Widget::TableData(__('None'), 'inactive');
					}
					
					if ($index) {
						if($index['weighting'] == '') $index['weighting'] = 2;
						$col_weighting = Widget::TableData($this->_weightings[$index['weighting']]);
					} else {
						$col_weighting = Widget::TableData(__('None'), 'inactive');
					}
					
					$count_data = null;
					$count_class = null;
					
					if (isset($_GET['section']) && in_array($section->get('id'), $re_index) && in_array($section->get('id'), array_keys($this->_indexes))) {
						SearchIndex::deleteIndexBySection($section->get('id'));
						$count_data = '<span class="to-re-index" id="section-'.$section->get('id').'">' . __('Waiting to re-index...') . '</span>';
					}
					else if (isset($this->_indexes[$section->get('id')])) {
						$count = Symphony::Database()->fetchCol(
							'count',
							sprintf(
								"SELECT COUNT(entry_id) as `count` FROM tbl_search_index WHERE `section_id`='%d'",
								$section->get('id')
							)
						);
						$count_data = $count[0] . ' ' . (((int)$count[0] == 1) ? __('entry') : __('entries'));
					}
					else {
						$count_data = __('No index');
						$count_class = 'inactive';
					}
					
					$col_count = Widget::TableData($count_data, $count_class . ' count-column');
					
					$tableBody[] = Widget::TableRow(array($col_name, $col_fields, $col_weighting, $col_count), 'section-' . $section->get('id'));

				}
			}
			
			$table = Widget::Table(
				Widget::TableHead($tableHead), null, 
				Widget::TableBody($tableBody),
				'selectable'
			);
			
			$this->Form->appendChild($table);
			
			$actions = new XMLElement('div');
			$actions->setAttribute('class', 'actions');
			
			$options = array(
				array(null, false, __('With Selected...')),
				array('re-index', false, __('Re-index Entries')),
				array('delete', false, __('Delete')),
			);
			
			$actions->appendChild(Widget::Select('with-selected', $options));
			$actions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));
			
			$this->Form->appendChild($actions);

		}
	}