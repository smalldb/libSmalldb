{
    "_": "<?php printf('_%c%c}%c',34,10,10);__halt_compiler();?>",
    "blocks": {
        "header_item": {
            "block": "core/out/header",
            "x": 0,
            "y": 3,
            "in_val": {
                "level": 2,
                "text": "Smalldb machines",
                "slot_weight": 30
            }
        },
        "build_types_menu": {
            "block": "smalldb/build_types_menu",
            "x": 170,
            "y": 0
        },
        "menu": {
            "block": "core/out/menu",
            "x": 427,
            "y": 0,
            "in_con": {
                "enable": [
                    "build_types_menu",
                    "done"
                ],
                "items": [
                    "build_types_menu",
                    "items"
                ]
            },
            "in_val": {
                "title_fmt": "{a}{type}{/a}"
            }
        }
    }
}