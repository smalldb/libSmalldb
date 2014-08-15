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

function TPL_html5__smalldb__heading($t, $id, $d, $so)
{
	extract($d);

	$tag = 'h'.$level;

	echo "<$tag";
	if ($anchor) {
		echo " name=\"", htmlspecialchars($anchor), "\"";
	}
	echo ">";

	echo htmlspecialchars($text);
	
	echo "</$tag>";

	if (!empty($links)) {
		echo "<div class=\"heading_links\">\n";
		$first = true;
		foreach ($links as $link) {
			if ($first) {
				$first = false;
			} else {
				echo "| ";
			}
			echo "<a href=\"", htmlspecialchars($link['link']), "\" class=\"", htmlspecialchars($link['class']), "\">",
				htmlspecialchars($link['label']), "</a>\n";
		}
		echo "</div>\n";
	}
}

