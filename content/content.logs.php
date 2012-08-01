<?php
	
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(EXTENSIONS . '/search_index/lib/class.search_index.php');
	
	class contentExtensionSearch_IndexLogs extends AdministrationPage {
		protected $_errors = array();
		
		public function __construct(){
			parent::__construct();			
			$this->_uri = SYMPHONY_URL . '/extension/search_index';
		}
		
		public function __viewIndex() {
			$this->setPageType('table');
			$this->setTitle(__('Symphony') . ' &ndash; ' . __('Search Indexes'));
			
			$page = (@(integer)$_GET['pg'] > 1 ? (integer)$_GET['pg'] : 1);
			$page_size = (int)Symphony::Configuration()->get('pagination_maximum_rows', 'symphony');
			
			$sort_column = 'date';
			$sort_order = 'desc';
			$filter_keywords = '';
			$filter_view = '';
			
			if (isset($_GET['sort'])) $sort_column = $_GET['sort'];
			if (isset($_GET['order'])) $sort_order = $_GET['order'];
			if (isset($_GET['keywords'])) $filter_keywords = $_GET['keywords'];
			if (isset($_GET['view'])) $filter_view = $_GET['view'];
			
			$logs = SearchIndex::getLogs($sort_column, $sort_order, ($filter_view == 'export') ? NULL : $page, $filter_keywords);
			
			if($filter_view == 'export') {
				
				$file_path = sprintf('%s/search-index.log.%d.csv', TMP, time());
				$csv = fopen($file_path, 'w');
				
				fputcsv($csv, array(__('Date'), __('Keywords'), __('Adjusted Keywords'), __('Results'), __('Depth'), __('Session ID')), ',', '"');
				
				foreach($logs as $log) {
					fputcsv($csv, array(
						$log['date'],
						$log['keywords'],
						$log['keywords_manipulated'],
						$log['results'],
						$log['depth'],
						$log['session_id']
					), ',', '"');
				}
				
				fclose($csv);
				
				header('Content-type: application/csv');
				header('Content-Disposition: attachment; filename="' . end(explode('/', $file_path)) . '"');
				readfile($file_path);
				unlink($file_path);
				
				exit;
				
			}
			
			$start = max(1, (($page - 1) * $page_size));
			$end = ($start == 1 ? $page_size : $start + count($logs));
			$total = SearchIndex::countLogs($filter_keywords);
			$pages = ceil($total / $page_size);
			
			$filter_form = Widget::Form($this->_uri . '/logs/', 'get');
			$filters = new XMLElement('div', NULL, array('class' => 'search-index-log-filters'));
			$label = new XMLElement('label', __('Filter searches containing the keywords %s', array(Widget::Input('keywords', $filter_keywords)->generate())));
			$filters->appendChild($label);
			$filters->appendChild(new XMLElement('input', NULL, array('type' => 'submit', 'value' => __('Filter'), 'class' => 'create button')));
			$filters->appendChild(Widget::Anchor(__('Clear'), $this->_uri . '/logs/', NULL, 'button clear'));
			$filter_form->appendChild($filters);
			
			$this->insertDrawer(Widget::Drawer('search_index', __('Filter Logs'), $filter_form, 'opened'), 'horizontal');

			$this->appendSubheading(__('Logs'),
				Widget::Anchor(__('Export CSV'), $this->_uri . '/logs/?view=export&amp;sort='.$sort_column.'&amp;order='.$sort_order.'&amp;keywords='.$filter_keywords, NULL, 'button')
			);
			
			$stats = array(
				'unique-users' => SearchIndex::getStatsCount('unique-users', $filter_keywords),
				'unique-searches' => SearchIndex::getStatsCount('unique-searches', $filter_keywords),
				'unique-terms' => SearchIndex::getStatsCount('unique-terms', $filter_keywords),
				'average-results' => SearchIndex::getStatsCount('average-results', $filter_keywords)
			);
			
			$this->addStylesheetToHead(URL . '/extensions/search_index/assets/search_index.css', 'screen', 100);
			
			$this->Form->appendChild(new XMLElement('p', sprintf(__('<strong>%s</strong> unique searches from <strong>%s</strong> unique users via <strong>%s</strong> distinct search terms. Each search yielded an average of <strong>%s</strong> results.', array($stats['unique-searches'], $stats['unique-users'], $stats['unique-terms'], $stats['average-results']))), array('class' => 'intro')));
			
			$tableHead = array();
			$tableBody = array();
			
			$tableHead = array(
				array(Widget::Anchor(__('Date'), Administration::instance()->getCurrentPageURL() . '?pg=1&amp;sort=date&amp;order=' . (($sort_column == 'date' && $sort_order == 'desc') ? 'asc' : 'desc') . '&amp;keywords=' . $filter_keywords, '', ($sort_column=='date' ? 'active' : '')), 'col'),
				array(Widget::Anchor(__('Keywords'), Administration::instance()->getCurrentPageURL() . '?pg=1&amp;sort=keywords&amp;order=' . (($sort_column == 'keywords' && $sort_order == 'asc') ? 'desc' : 'asc') . '&amp;keywords=' . $filter_keywords, '', ($sort_column=='keywords' ? 'active' : '')), 'col'),
				array(__('Adjusted Keywords'), 'col'),
				array(Widget::Anchor(__('Results'), Administration::instance()->getCurrentPageURL() . '?pg=1&amp;sort=results&amp;order=' . (($sort_column == 'results' && $sort_order == 'desc') ? 'asc' : 'desc') . '&amp;keywords=' . $filter_keywords, '', ($sort_column=='results' ? 'active' : '')), 'col'),
				array(Widget::Anchor(__('Depth'), Administration::instance()->getCurrentPageURL() . '?pg=1&amp;sort=depth&amp;order=' . (($sort_column == 'depth' && $sort_order == 'desc') ? 'asc' : 'desc') . '&amp;keywords=' . $filter_keywords, '', ($sort_column=='depth' ? 'active' : '')), 'col'),
				array(__('Session ID'), 'col'),
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
					
					$row[] = Widget::TableData(htmlentities($keywords, ENT_QUOTES), $keywords_class);
					
					$adjusted = $log['keywords_manipulated'];
					$adjusted_class = '';
					if ($log['keywords_manipulated'] == '' || strtolower(trim($log['keywords'])) == strtolower(trim($log['keywords_manipulated']))) {
						$adjusted = __('None');
						$adjusted_class = 'inactive';
					}

					$row[] = Widget::TableData(htmlentities($adjusted, ENT_QUOTES), $adjusted_class);
					$row[] = Widget::TableData($log['results']);
					$row[] = Widget::TableData($log['depth']);
					$row[] = Widget::TableData($log['session_id']);
					//$row[] = Widget::TableData($log['session_id'] . Widget::Input("items[{$log['id']}]", null, 'checkbox')->generate());
					
					$tableBody[] = Widget::TableRow($row);
				}
			}
			
			$table = Widget::Table(
				Widget::TableHead($tableHead), null, 
				Widget::TableBody($tableBody)
			);
			
			$this->Form->appendChild($table);
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');						
			$this->Form->appendChild($div);
						
			// Pagination:
			if ($pages > 1) {
				$ul = new XMLElement('ul');
				$ul->setAttribute('class', 'page');
				
				## First
				$li = new XMLElement('li');
				if ($page > 1) {
					$li->appendChild(Widget::Anchor(__('First'), Administration::instance()->getCurrentPageURL() . '?pg=1&amp;sort='.$sort_column.'&amp;order='.$sort_order.'&amp;keywords='.$filter_keywords));
				} else {
					$li->setValue(__('First'));
				}
				$ul->appendChild($li);
				
				## Previous
				$li = new XMLElement('li');
				if ($page > 1) {
					$li->appendChild(Widget::Anchor(__('&larr; Previous'), Administration::instance()->getCurrentPageURL(). '?pg='.($page-1).'&amp;sort='.$sort_column.'&amp;order='.$sort_order.'&amp;keywords='.$filter_keywords));
				} else {
					$li->setValue('&larr; ' . __('Previous'));
				}				
				$ul->appendChild($li);

				## Summary
				$li = new XMLElement('li');
				$li->setAttribute('title', __('Viewing %1$s - %2$s of %3$s entries', array(
					$start,
					$end,
					$total
				)));

				$pgform = Widget::Form(Administration::instance()->getCurrentPageURL(), 'get', 'paginationform');
				$pgmax = max($page, $pages);
				$pgform->appendChild(Widget::Input('pg', NULL, 'text', array(
					'data-active' => __('Go to page …'),
					'data-inactive' => __('Page %1$s of %2$s', array((string)$page, $pgmax)),
					'data-max' => $pgmax
				)));

				$li->appendChild($pgform);
				$ul->appendChild($li);

				## Next
				$li = new XMLElement('li');				
				if ($page < $pages) {
					$li->appendChild(Widget::Anchor(__('Next &rarr;'), Administration::instance()->getCurrentPageURL(). '?pg='.($page+1).'&amp;sort='.$sort_column.'&amp;order='.$sort_order.'&amp;keywords='.$filter_keywords));
				} else {
					$li->setValue(__('Next') . ' &rarr;');
				}				
				$ul->appendChild($li);

				## Last
				$li = new XMLElement('li');
				if ($page < $pages) {
					$li->appendChild(Widget::Anchor(__('Last'), Administration::instance()->getCurrentPageURL(). '?pg='.$pages.'&amp;sort='.$sort_column.'&amp;order='.$sort_order.'&amp;keywords='.$filter_keywords));
				} else {
					$li->setValue(__('Last'));
				}				
				$ul->appendChild($li);
				
				$this->Contents->appendChild($ul);
			}

		}
	}
