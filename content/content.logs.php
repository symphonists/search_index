<?php
	
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(EXTENSIONS . '/search_index/lib/class.search_index.php');
	
	class contentExtensionSearch_IndexLogs extends AdministrationPage {
		protected $_errors = array();
		
		public function __construct(&$parent){
			parent::__construct($parent);			
			$this->_uri = URL . '/symphony/extension/search_index';
		}
		
		public function build($context) {
			parent::build($context);
		}
		
		public function __actionIndex() {
			$checked = @array_keys($_POST['items']);
			
			if (is_array($checked) and !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						// TODO: delete from log
						break;
				}
			}
		}
						
		public function __viewIndex() {
			$this->setPageType('table');
			$this->setTitle(__('Symphony') . ' &ndash; ' . __('Search Indexes'));
			
			$page = (@(integer)$_GET['pg'] > 1 ? (integer)$_GET['pg'] : 1);
			$page_size = (int)Symphony::Configuration()->get('pagination_maximum_rows', 'symphony');
			
			$sort_column = 'date';
			$sort_order = 'desc';
			
			if (isset($_GET['sort'])) $sort_column = $_GET['sort'];
			if (isset($_GET['order'])) $sort_order = $_GET['order'];
			
			//echo $logs_sql;die;
			$logs = SearchIndex::getLogs($sort_column, $sort_order, $page);
						
			$start = max(1, (($page - 1) * $page_size));
			$end = ($start == 1 ? $page_size : $start + count($logs));
			$total = SearchIndex::countLogs();
			$pages = ceil($total / $page_size);
			
			$this->appendSubheading(__('Search Index') . " &raquo; " . __('Logs'));
			
			$this->addStylesheetToHead(URL . '/extensions/search_index/assets/search_index.css', 'screen', 100);
			$this->addScriptToHead(URL . '/extensions/search_index/assets/search_index.js', 100);
			
			$tableHead = array();
			$tableBody = array();
			
			$tableHead = array(
				array(Widget::Anchor('Date', Administration::instance()->getCurrentPageURL() . '?pg=1&amp;sort=date&amp;order=' . (($sort_column == 'date' && $sort_order == 'desc') ? 'asc' : 'desc'), '', ($sort_column=='date' ? 'active' : '')), 'col'),
				array(Widget::Anchor('Keywords', Administration::instance()->getCurrentPageURL() . '?pg=1&amp;sort=keywords&amp;order=' . (($sort_column == 'keywords' && $sort_order == 'asc') ? 'desc' : 'asc'), '', ($sort_column=='keywords' ? 'active' : '')), 'col'),
				array('Adjusted Keywords', 'col'),
				array(Widget::Anchor('Results', Administration::instance()->getCurrentPageURL() . '?pg=1&amp;sort=results&amp;order=' . (($sort_column == 'results' && $sort_order == 'desc') ? 'asc' : 'desc'), '', ($sort_column=='results' ? 'active' : '')), 'col'),
				array(Widget::Anchor('Depth', Administration::instance()->getCurrentPageURL() . '?pg=1&amp;sort=depth&amp;order=' . (($sort_column == 'depth' && $sort_order == 'desc') ? 'asc' : 'desc'), '', ($sort_column=='depth' ? 'active' : '')), 'col'),
				array('Sesion ID', 'col'),
			);
			
			if (!is_array($logs) or empty($logs)) {
				$tableBody = array(
					Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', null, count($tableHead))))
				);
			}
			
			else {
				
				foreach ($logs as $hash => $log) {
					$row = array();
					
					$row[] = Widget::TableData(DateTimeObj::get(__SYM_DATETIME_FORMAT__, strtotime($log['date'])));
					
					$keywords = $log['keywords'];
					$keywords_class = '';
					if ($keywords == '') {
						$keywords = __('None');
						$keywords_class = 'inactive';
					}
					
					$row[] = Widget::TableData($keywords, $keywords_class);
					
					$adjusted = $log['keywords_manipulated'];
					$adjusted_class = '';
					if ($log['keywords_manipulated'] == '' || strtolower(trim($log['keywords'])) == strtolower(trim($log['keywords_manipulated']))) {
						$adjusted = __('None');
						$adjusted_class = 'inactive';
					}
					
					$row[] = Widget::TableData($adjusted, $adjusted_class);
					$row[] = Widget::TableData($log['results']);
					$row[] = Widget::TableData($log['depth']);
					$row[] = Widget::TableData($log['session_id']);
					
					$tableBody[] = Widget::TableRow($row);
				}
			}
			
			$table = Widget::Table(
				Widget::TableHead($tableHead), null, 
				Widget::TableBody($tableBody)
			);
			
			$this->Form->appendChild($table);
						
			$actions = new XMLElement('div');
			$actions->setAttribute('class', 'actions');
			
			$options = array(
				array(NULL, FALSE, 'With Selected...'),
				array('delete', FALSE, 'Delete'),
			);
			
			$actions->appendChild(Widget::Select('with-selected', $options));
			$actions->appendChild(Widget::Input('action[apply]', 'Apply', 'submit'));
			
			$this->Form->appendChild($actions);
			
			// Pagination:
			if ($pages > 1) {
				$ul = new XMLElement('ul');
				$ul->setAttribute('class', 'page');
				
				## First
				$li = new XMLElement('li');
				if ($page > 1) {
					$li->appendChild(Widget::Anchor(__('First'), Administration::instance()->getCurrentPageURL() . '?pg=1'));
				} else {
					$li->setValue('First');
				}
				$ul->appendChild($li);
				
				## Previous
				$li = new XMLElement('li');
				if ($page > 1) {
					$li->appendChild(Widget::Anchor(__('&larr; Previous'), Administration::instance()->getCurrentPageURL(). '?pg=' . ($page - 1)));
				} else {
					$li->setValue('&larr; Previous');
				}				
				$ul->appendChild($li);

				## Summary
				$li = new XMLElement('li', __('Page %1$s of %2$s', array($page, max($page, $pages))));
				$li->setAttribute('title', __('Viewing %1$s - %2$s of %3$s entries', array(
					$start,
					$end,
					$total
				)));
				$ul->appendChild($li);

				## Next
				$li = new XMLElement('li');				
				if ($page < $pages) {
					$li->appendChild(Widget::Anchor(__('Next &rarr;'), Administration::instance()->getCurrentPageURL(). '?pg=' . ($page + 1)));
				} else {
					$li->setValue('Next &rarr;');
				}				
				$ul->appendChild($li);

				## Last
				$li = new XMLElement('li');
				if ($page < $pages) {
					$li->appendChild(Widget::Anchor(__('Last'), Administration::instance()->getCurrentPageURL(). '?pg=' . $pages));
				} else {
					$li->setValue('Last');
				}				
				$ul->appendChild($li);
				
				$this->Form->appendChild($ul);	
			}

		}
	}