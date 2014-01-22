{
    "_": "<?php printf('_%c%c}%c',34,10,10);__halt_compiler();?>",
    "outputs": {
        "title": [
            "describe:type"
        ],
        "done": [
            "describe:done"
        ]
    },
    "blocks": {
        "header_item": {
            "block": "core/out/header",
            "x": 261,
            "y": 0,
            "in_val": {
                "level": 2,
                "text": "Smalldb machine: {machine_type}",
                "slot_weight": 30
            },
            "in_con": {
                "machine_type": [
                    "describe",
                    "type"
                ]
            }
        },
        "show_diagram": {
            "block": "smalldb/show_diagram",
            "x": 261,
            "y": 207,
            "in_con": {
                "machine_type": [
                    "describe",
                    "type"
                ]
            },
            "in_val": {
                "slot_weight": 40
            }
        },
        "describe": {
            "block": "smalldb/describe_machine",
            "x": 0,
            "y": 114,
            "in_con": {
                "type": [
                    "admin",
                    "machine_type"
                ]
            }
        },
        "show_properties": {
            "block": "smalldb/show_properties",
            "x": 262,
            "y": 352,
            "in_con": {
                "desc": [
                    "describe",
                    "desc"
                ]
            },
            "in_val": {
                "slot_weight": 70
            }
        }
    }
}