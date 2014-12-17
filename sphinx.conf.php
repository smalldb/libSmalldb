#!/usr/bin/env php
<?php
/*
 * Copyright (c) 2014, Josef Kufner  <jk@frozen-doe.net>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */
/**
 * Sphinx Configuration Generator
 *
 * Symlink this file from /etc/sphinxsearch/conf.d directory
 * or /etc/shinxsearch/sphinx.conf. Since this file is executable, Sphinx will
 * execute it and use its output as config file. This script can be executed
 * only from command line by root, sphinxsearch user, or owner.
 *
 * This script extracts only database credentials. You can inherit generated
 * configuration like this:
 *
 *     source custom_source : database {
 *         sql_query = ...
 *         sql_attr_uint = ...
 *         ...
 *     }
 *
 *     index custom_index : database {
 *         source = custom_source
 *         path = /var/lib/sphinxsearch/data/custom_index
 *     }
 *
 * Where `database` is generated configuration and `custom_source` is the new
 * source. The same with indexes.
 *
 * TODO: Refector this to some nice class.
 */

function fail($msg) {
	error_log(__FILE__.': '.$msg);
	exit(-1);
}

// Local only
if (!empty($_SERVER['REMOTE_ADDR']) || php_sapi_name() != 'cli') {
        fail("Please execute this from your command line!");
}

// Initialize framework
list($default_context, $core_cfg) = require(dirname(dirname(dirname(__FILE__))).'/core/init.php');

// Load configuration
if (!isset($core_cfg['sphinxsearch'])) {
	fail("Section \"sphinxsearch\" not found in core config.");
}
$sphinx_conf = $core_cfg['sphinxsearch'];

// Check users
$userinfo = posix_getpwuid(posix_getuid());
$username = $userinfo['name'];
if (empty($sphinx_conf['allowed_users'][$username]) && $username != get_current_user()) {
	fail("Unauthorized by \"sphinxsearch.allowed_users\".");
}

// Load database access options
if (!isset($sphinx_conf['database_resource_name'])) {
	fail("No database resource name specified.");
}
$database_resource_name = $sphinx_conf['database_resource_name'];
if (!isset($core_cfg['context']['resources'][$database_resource_name])) {
	fail("Database resource does not exist.");
}
$database_resource_conf = $core_cfg['context']['resources'][$database_resource_name];

// Load Smalldb resource
if (!isset($sphinx_conf['smalldb_resource_name'])) {
	fail("No Smalldb resource name specified.");
}
$smalldb_resource_name = $sphinx_conf['smalldb_resource_name'];

// Determine prefix (database name by default)
if (isset($sphinx_conf['name_prefix'])) {
	$name_prefix = $sphinx_conf['name_prefix'];
} else {
	$name_prefix = $database_resource_conf['database'];
}

// Check driver -- MySQL only
if ($database_resource_conf['driver'] != 'mysql') {
	fail("Unsupported database driver.");
}

$base_name = $name_prefix . '_' . $database_resource_name;

// Print config file
echo <<<EOF
# abstract source with db credentials
source {$base_name}
{
        type = {$database_resource_conf['driver']}
        sql_host = {$database_resource_conf['host']}
        sql_user = {$database_resource_conf['username']}
        sql_db = {$database_resource_conf['database']}
        sql_pass = {$database_resource_conf['password']}
        sql_sock = /var/run/mysqld/mysqld.sock

        sql_query_pre = SET NAMES utf8
	sql_query_pre = SET SESSION query_cache_type=OFF

	# index nothing, just suppress warning since this index is abstract
	# 1st column is ID, 2nd column is state of the machine
	sql_query = SELECT NULL AS sphinx_key, NULL AS state LIMIT 0
	sql_attr_string = state
}

# abstract index
index {$base_name}
{
        source = {$base_name}
        path = /var/lib/sphinxsearch/data/{$base_name}
        charset_type = utf-8
        enable_star = 1
        min_infix_len = 2
        min_word_len = 2
        html_strip = 1
        html_remove_elements = style, script

        charset_table = \
                U+021, U+023, U+025, U+027, U+030..U+039, U+040..U+05a, U+07e, U+0b5, U+0c6, \
                U+0d0, U+0d8, U+0de, U+0df, U+110, U+126, U+132, U+138, U+13f, U+141, U+149, U+14a, \
                U+166, U+2019->U+027, U+061->U+041, U+0c0->U+041, U+0c1->U+041, U+0c2->U+041, \
                U+0c3->U+041, U+0c4->U+041, U+0c5->U+041, U+0e0->U+041, U+0e1->U+041, U+0e2->U+041, \
                U+0e3->U+041, U+0e4->U+041, U+0e5->U+041, U+100->U+041, U+101->U+041, U+102->U+041, \
                U+103->U+041, U+104->U+041, U+105->U+041, U+062->U+042, U+063->U+043, U+0c7->U+043, \
                U+0e7->U+043, U+106->U+043, U+107->U+043, U+108->U+043, U+109->U+043, U+10a->U+043, \
                U+10b->U+043, U+10c->U+043, U+10d->U+043, U+064->U+044, U+10e->U+044, U+10f->U+044, \
                U+065->U+045, U+0c8->U+045, U+0c9->U+045, U+0ca->U+045, U+0cb->U+045, U+0e8->U+045, \
                U+0e9->U+045, U+0ea->U+045, U+0eb->U+045, U+112->U+045, U+113->U+045, U+114->U+045, \
                U+115->U+045, U+116->U+045, U+117->U+045, U+118->U+045, U+119->U+045, U+11a->U+045, \
                U+11b->U+045, U+066->U+046, U+067->U+047, U+11c->U+047, U+11d->U+047, U+11e->U+047, \
                U+11f->U+047, U+120->U+047, U+121->U+047, U+122->U+047, U+123->U+047, U+068->U+048, \
                U+124->U+048, U+125->U+048, U+069->U+049, U+0cc->U+049, U+0cd->U+049, U+0ce->U+049, \
                U+0cf->U+049, U+0ec->U+049, U+0ed->U+049, U+0ee->U+049, U+0ef->U+049, U+128->U+049, \
                U+129->U+049, U+12a->U+049, U+12b->U+049, U+12c->U+049, U+12d->U+049, U+12e->U+049, \
                U+12f->U+049, U+130->U+049, U+131->U+049, U+06a->U+04a, U+134->U+04a, U+135->U+04a, \
                U+06b->U+04b, U+136->U+04b, U+137->U+04b, U+06c->U+04c, U+139->U+04c, U+13a->U+04c, \
                U+13b->U+04c, U+13c->U+04c, U+13d->U+04c, U+13e->U+04c, U+06d->U+04d, U+06e->U+04e, \
                U+0d1->U+04e, U+0f1->U+04e, U+143->U+04e, U+144->U+04e, U+145->U+04e, U+146->U+04e, \
                U+147->U+04e, U+148->U+04e, U+06f->U+04f, U+0d2->U+04f, U+0d3->U+04f, U+0d4->U+04f, \
                U+0d5->U+04f, U+0d6->U+04f, U+0f2->U+04f, U+0f3->U+04f, U+0f4->U+04f, U+0f5->U+04f, \
                U+0f6->U+04f, U+14c->U+04f, U+14d->U+04f, U+14e->U+04f, U+14f->U+04f, U+150->U+04f, \
                U+151->U+04f, U+070->U+050, U+071->U+051, U+072->U+052, U+154->U+052, U+155->U+052, \
                U+156->U+052, U+157->U+052, U+158->U+052, U+159->U+052, U+073->U+053, U+15a->U+053, \
                U+15b->U+053, U+15c->U+053, U+15d->U+053, U+15e->U+053, U+15f->U+053, U+160->U+053, \
                U+161->U+053, U+17f->U+053, U+074->U+054, U+162->U+054, U+163->U+054, U+164->U+054, \
                U+165->U+054, U+075->U+055, U+0d9->U+055, U+0da->U+055, U+0db->U+055, U+0dc->U+055, \
                U+0f9->U+055, U+0fa->U+055, U+0fb->U+055, U+0fc->U+055, U+168->U+055, U+169->U+055, \
                U+16a->U+055, U+16b->U+055, U+16c->U+055, U+16d->U+055, U+16e->U+055, U+16f->U+055, \
                U+170->U+055, U+171->U+055, U+172->U+055, U+173->U+055, U+076->U+056, U+077->U+057, \
                U+174->U+057, U+175->U+057, U+078->U+058, U+079->U+059, U+0dd->U+059, U+0fd->U+059, \
                U+0ff->U+059, U+176->U+059, U+177->U+059, U+178->U+059, U+07a->U+05a, U+179->U+05a, \
                U+17a->U+05a, U+17b->U+05a, U+17c->U+05a, U+17d->U+05a, U+17e->U+05a, U+0e6->U+0c6, \
                U+0f0->U+0d0, U+0f8->U+0d8, U+0fe->U+0de, U+111->U+110, U+127->U+126, U+133->U+132, \
                U+140->U+13f, U+142->U+141, U+14b->U+14a, U+153->U+152, U+167->U+166
}


EOF;

// Scan all Smalldb machines and generate index configuration
$smalldb = $default_context->$smalldb_resource_name;
$type_map = $sphinx_conf['type_map'];
foreach ($smalldb->getKnownTypes() as $type_name) {
	$desc = $smalldb->describeType($type_name);
	if (!isset($desc['sphinxsearch']) || empty($desc['sphinxsearch']['index_enabled'])) {
		continue;
	}

	//print_r($desc['sphinxsearch']);

	$source_name = $name_prefix . '_' . $type_name;

	if (isset($desc['sphinxsearch']['sphinx_key_sql'])) {
		$sphinx_key = $desc['sphinxsearch']['sphinx_key_sql'];
	} else {
		$sphinx_key = '`sphinx_key`';
	}

	// Build query
	$listing = $smalldb->createListing(array(
		'type' => $type_name,
		'limit' => false,
		'offset' => false,
		'order_by' => null
	));
	$query = $listing->getQueryBuilder();
	$query->selectFirst("$sphinx_key as `sphinx_key`");			// prepend sphinx key to columns
	$sql_query = str_replace("\n", " \\\n\t", $query->getSqlQuery());	// FIXME: what if there are placeholders?

	echo "source {$source_name} : {$base_name} {\n";
	echo "	sql_query = \\\n";
	echo "	{$sql_query}\t;\n";
	echo "	sql_attr_string = state\n";
	foreach ($listing->describeProperties() as $property => $p) {
		if (isset($p['sphinx_type'])) {
			$t = $p['sphinx_type'];
		} else if (isset($type_map[$p['type']])) {
			$t = $type_map[$p['type']];
		} else {
			$t = 'string';
		}

		switch ($t) {
			case 'fulltext':
				// indexed
				break;
			case 'string':
			case 'uint':
			case 'bool':
			case 'bigint':
			case 'timestamp':
			case 'float':
			case 'string':
			case 'json':
				echo "	sql_attr_", $t, " = ", $property, "\n";
				break;
			default:
				fail('Unknown type "'.$t.'" of attribute "'.$property.'".');
				break;
		}
	}
	echo "}\n\n";

	echo "index {$source_name} : {$base_name} {\n";
	echo "	source = {$source_name}\n";
	echo "	path = /var/lib/sphinxsearch/data/{$source_name}\n";
	echo "}\n\n";
}


