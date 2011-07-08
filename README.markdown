# Search Index

* Version: 0.9.1
* Author: [Nick Dunn](http://nick-dunn.co.uk)
* Build Date: 2011-07-08
* Requirements: Symphony 2.2

## Description
Search Index provides an easy way to implement high performance fulltext searching on your Symphony site. By setting filters for each Section in your site you control which entries are indexed and therefore searchable. Frontend search can be implemented either using the Search Index field that allows keyword filtering in data sources, or the included Search Index data source for searching multiple sections at once.

## Usage
1. Add the `search_index` folder to your Extensions directory
2. Enable the extension from the Extensions page
3. Configure indexes from Search Index > Indexes

### 1. Configuring section indexes
After installation navigate to Search Index > Indexes whereupon you will see a list of all sections in your site. Click on a name to configure the indexing criteria for that section. The index editor works the same as the data source editor:

* Only values of fields selected from the **Included Elements** list are used for searching
* **Index Filters** work exactly like data source filters. Use these to ensure only desired entries are indexed

Once saved the "Index" column will display "0 entries" on the Search Indexes page. Select the row and choose "Re-index Entries" from the With Selected menu. When the page reloads you will see the index being rebuilt, one page of results at a time.

Multiple sections can be selected at once for re-indexing.

The page size and speed of refresh can be modified by editing the `re-index-per-page` and `re-index-refresh-rate` variables in your Symphony `config.php`.

### 2. Fulltext search in a data source (single section)
Adding a keyword search to an existing data source is extremely easy. Start by adding the Search Index field to your section. This allows you to add a filter on this field when building a data source. For example:

* add the Search Index field to your section
* modify your data source to filter this field with a filter value of `{$url-keywords}`
* attach the data source to a page and access like `/my-page/?keywords=foo+bar`

### 3. Fulltext search across multiple Sections
A full-site search can be achieved using the custom Search Index data source included with this extension. Attach this data source to a page and invoke it using the following GET parameters:

* `keywords` the string to search on e.g. `foo bar`
* `sort` (default `score`) either `id` (entry ID), `date` (entry creation date), `score` (relevance) or `score-recency` (relevance with a higher weighting for newer entries)
* `direction` (default `desc`) either `asc` or `desc`
* `per-page` (default `20`) number of results per page
* `page` the results page number
* `sections` a comma-delimited list of section handles to search within (only those with indexes will work) e.g. `articles,comments`

Your search form might look like this:

	<form action="/search/" method="get">
		<label>Search <input type="text" name="keywords" /></label>
		<input type="hidden" name="sort" value="score-recency" />
		<input type="hidden" name="per-page" value="10" />
		<input type="hidden" name="sections" value="articles,comments,categories" />
	</form>

Note that all of these variables (except for `keywords`) **have defaults** in `config.php`. So if you would rather not include these on your URLs, modify the defaults there and omit them from your HTML.

If you want to change the **name** of these variables, they can be modified in your Symphony `config.php`. If you are using Form Controls to post these variables from a form your variable names may be in the form `fields[...]`. If so, add `fields` to the `get-param-prefix` variable in your Symphony `config.php`. For more on renaming variables please see the "Configuration" section in this README for an example.

#### Using Symphony URL Parameters
The default is to use GET parameters such as `/search/?keywords=foo+bar&page=2` but if you prefer to use URL Parameters such as `/search/foo+bar/2/`, set the `get-param-prefix` variable to a value of `param_pool` in your `config.php` and the extension will look at the Param Pool rather than the $_GET array for its values.

#### Example XML

The XML returned from this data source looks like this:

	<search keywords="foo+bar+symfony" sort="score" direction="desc">
		<alternative-keywords>
			<keyword original="foo" alternative="food" distance="1" />
			<keyword original="symfony" alternative="symphony" distance="2" />
		</alternative-keywords>
		<pagination total-entries="5" total-pages="1" entries-per-page="20" current-page="1" />
		<sections>
			<section id="1" handle="articles">Articles</section>
			<section id="2" handle="comments">Comments</section>
		</sections>
		<entry id="3" section="comments">
			<excerpt>...</excerpt>
		</entry>
		<entry id="5" section="articles">
			<excerpt>...</excerpt>
		</entry>
		<entry id="2" section="articles">
			<excerpt>...</excerpt>
		</entry>
		<entry id="1" section="comments">
			<excerpt>...</excerpt>
		</entry>
		<entry id="3" section="comments">
			<excerpt>...</excerpt>
		</entry>
	</search>

This in itself is not enough to render a results page. To do so, use the `$ds-search` Output Parameter created by this data source to filter by System ID in other data sources. In the example above you would create a new data source each for Articles and Comments, filtering System ID by the `$ds-search` parameter. Use XSLT to iterate over the `<entry ... />` elements above, and cross-reference with the matching entries from the Articles and Comments data sources.

(But if you're very lazy and don't give two-hoots about performance, see the `build-entries` config option explained later.)

## Weighting
We all know that all sections are equal, only some are more equal than others ;-) You can give higher or lower weighting to results from certain sections, by issuing them a weighting when you configure their Search Index. The default is `Medium` (no weighting), but if you want more chance of entries from your section appearing higher up the search results, choose `High`; or for even more prominence `Highest`. The opposite is true: to bury entries lower down the results then choose `Low` or `Lowest`. This weighting has the effect of doubling/quadrupling or halving/quartering the original "relevance" score calculated by the search.

## Configuration
The common configuration options are discussed above. This is a full list of the variables you *should* see in your `config.php`. If some are missing it is because you have previously installed an earlier version of the extension. You can add these variables manually to make use of them.

### `re-index-per-page`
Defaults to `20`. When manually re-indexing sections in the backend (Search Index > Indexes, highlight rows an select "Re-index" from the With Selected dropdown) this is the number of entries per "page" that will be re-indexed at once. If you have 100 entries and `re-index-per-page` is `20` then you will have 5 pages of entries that will index, one after the other.

### `re-index-refresh-rate`
Defaults to `0.5` seconds. This is the "pause" between each cycle of indexing when manually re-indexing sections. If you have a high traffic site (or slow server) and you are worried that many consecutive page refreshes will use too much server power, then choose a higher number and there will be a longer pause between each page of indexing. The larger the number, the longer you have to wait during re-indexing. Set to `0` for super-quick times.

### `min-word-length`
The smallest length of word to index. Words shorter than this will be ignored. If your site is technical and you need to index abbreviations such as `CSS` then make sure `min-word-length` is set to `3` to allow for these!

### `max-word-length`
The longest length of word to index. Words longer than this will be ignored. The maximum value this variable can be is limited by the database column size (currently `varchar(255)`).

### `stem-words`
Allow word stems to be included in searches. This usually results in more matches. The popular Porter Stemmer algorithm is used. Examples:

* summary, summarise => summar
* filters, filtering => filter

Note: I found a few oddities, namely words ending in `y` which are shortened to end in `i`. For example `symphony` and `entry` become `symphoni` and `entri` respectively. This is obviously incorrect, therefore the Porter algorithm is recommended for English-language sites only.

### `mode`
Three query modes are supported:

* `like` uses `LIKE '%...%'` syntax to match whole and partial words
* `regexp` uses `REGEXP [[:<:]]...[[:>:]]` syntax to match whole words only
* `fulltext` uses `MATCH(...) AGAINST(...)` syntax for MySQL's own fulltext binary search

Changing this variable changes the query mode for all searches made by this extension, both the Search Index data source and filtering on the Search Index field. Mode switching was introduced because of the limitations of fulltext binary search: while very fast, there is a word length limitation, and doesn't work well with short indexed strings or small data sets.

`like` is the default as this seems to provide the best compromise between performance, in-word matching, and narrowness of results returned.

Both `like` and `regexp` modes correctly handle boolean operators in search results:

* prefix a keyword with `+` to make it required
* prefix a keyword with `-` to make it forbidden
* surround a phrase with `"..."` to match the whole phrase

### `excerpt-length`
When using the Search Index data source, each matched entry will include an excerpt with search keywords highlighted in the text. The default length of this string is `250` characters, but modify it to suit your design.

### `build-entries`
By default the Search Index data source will only return an `<entry />` stub for each entry found. It is the developer's job to add additional data sources that filter using the search output parameter, in order to provide extra fields to build search results fully.

However, for the lazy amongst you, set this variable to `yes` and the entries will be built in their entirety in the data source. This has the benefit that you need only a single data source, but if your entries have many fields, then this will likely have a performance hit as you are adding fields to your XML that you don't need. With great power comes great responsibility, my son.

### `default-sections`
A comma-separated string of section handles to include in the search by default. If you would rather not pass these via a GET parameter to the search data source (e.g. `/search/?sections=articles,comments`) then add these to the config and omit them from the URL. Defaults to none.

### `default-per-page`
Default number of entries to show per page. Passing this value as a GET parameter to the search data source (e.g. `/search/?per-page=10`) overrides this default. Defaults to `20`.

### `default-sort`
Default field to sort results by. Passing this value as a GET parameter to the search data source (e.g. `/search/?sort=date`) overrides this default. Defaults to `score`.

### `default-direction`
Default direction to sort results by. Passing this value as a GET parameter to the search data source (e.g. `/search/?sort=asc`) overrides this default. Defaults to `desc`.

### `log-keywords`
When enabled, each unique search will be logged and be visible under Search Index > Logs.

### `get-param-*`
These variables store the _name_ of the GET parameter that the Search Index data source looks for. Change these if you don't like my choice of GET parameter names, or if you want them in your own language. For example:

	get-param-keywords' => 'term',
	get-param-per-page' => 'limit',
	get-param-sort' => 'order-by',
	get-param-direction' => 'order-direction',
	get-param-sections' => 'in',
	get-param-page' => 'p',

This would mean you'd create your search URL as:

	/?term=foo+bar&limit=20&order-by=id&order-direction=asc&in=articles,comments&p=2

The `get-param-prefix` variable is explained above in "Using Symphony URL Parameters".

### `indexes` and `synonyms`
These serialised arrays are created by saving settings from Search Index > Indexes and Search Index > Synonyms. Please don't edit them here, or bad things will happen to you.

## Synonyms

This allows you to configure word replacements so that commonly mis-spelt terms are automatically fixed, or terms with many alternative spellings or variations can be normalised to a single spelling. An example:

* Replacement word `United Kingdom`
* Synonyms: `uk, great britain, GB, united kingdoms`

When a user searches for any of the synonym words, they will be replaced by the replacement word. So if a user searches for `countries in the UK` their search will actually use the phrase `counties in the United Kingdom`. 

Synonym matches are _not_ case-sensitive.

## Auto-complete/auto-suggest

There is a "Search Index Suggestions" data source which can be used for auto-complete search inputs. Attach this data source to a page and pass two GET parameters:

* `keywords` is the keywords to search for (the start of words are matched, less than 3 chars are ignored)
* `sort` (optional) defaults to `alphabetical` but pass `frequency` to order words by the frequency in which they occur in your index
* `sections` (optional) a comma-delimited list of section handles to return keywords for (only those with indexes will work) e.g. `articles,comments`. If omitted all indexed sections are used.

This extension does not provide the JavaScript "glue" to build the auto-suggest or auto-complete functionality. There are plenty of jQuery plugins to do this for you, and each expect slightly different XML/JSON/plain text, so I have not attempted to implement this for you. Sorry, old chum.

## Log viewer

You can see what your users have searched for on the Search Index > Logs page. When logging is enabled, every search made through the Search Index data source will be stored. However the log viewer only displays _unique_ searches — if in one session a user searches using the same keywords four times, it will only display in the log viewer once.

Column descriptions:

* `Date` is the time of the search. If a user has searched multiple times, this is the time of the _first_search
* `Keywords` is the raw keyword phrase the user used
* `Adjusted Keywords` shows the keyword phrase if it was modified by synonym expansion
* `Results` is the number of matched entries the search yielded
* `Depth` is the maximum number of search results pages the user clicked through

## Known issues
* you can not order results by relevance score when using a single data source. This is only available when using the custom Search Index data source
* if you hit the word-length limitations using boolean fulltext searching, try an alternative `mode` (`like` or `regexp`).