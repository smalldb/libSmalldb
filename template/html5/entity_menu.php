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

function TPL_html5__smalldb__entity_menu($t, $id, $d, $so)
{
	extract($d);

	if (!empty($menu)) {
		echo "<div class=\"entity_menu\" id=\"", htmlspecialchars($id), "\">\n";

		// Trick
		echo "<input type=\"checkbox\" class=\"menu_opener\" id=\"", htmlspecialchars($id), "__check\"", $expanded ? ' checked' : '', ">";

		// Menu button
		echo "<label for=\"", htmlspecialchars($id), "__check\">", _('Entities'), "<i></i></label>\n";

		// Menu items
		echo "<div class=\"menu_items\">\n";
		foreach ($menu as $item) {
			echo "<a href=\"", htmlspecialchars($item['link']), "\">", htmlspecialchars($item['label']), "</a>\n";
		}
		echo "</div>\n";
		echo "</div>\n";
	}
}

