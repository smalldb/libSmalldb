{
	"_": "<?php printf('_%c%c}%c',34,10,10);__halt_compiler();?>",
	"main_menu": {
		"documentation": {
			"children": {
				"smalldb": {
					"title": "Smalldb machines",
					"weight": 40,
					"link": "/admin/doc/smalldb"
				}
			}
		}
	},
	"routes": {
		"/doc/smalldb": {
			"title": "Smalldb machines",
			"block": "smalldb/admin/doc_index",
			"connections": {
			}
		},
		"/doc/smalldb/$machine_type": {
			"title": "Smalldb machine",
			"block": "smalldb/admin/doc_show",
			"connections": {
			}
		}
	}
}

