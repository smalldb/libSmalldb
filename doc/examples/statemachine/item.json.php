{
    "_": "<?php printf('_%c%c}%c',34,10,10);__halt_compiler();?>",
    "description": "Simple CRUD entity.",
    "class": "\\Smalldb\\StateMachine\\ArrayMachine",
    "states": {
        "exists": {
            "description": "Some general item"
        }
    },
    "actions": {
        "create": {
            "description": "Create new item",
            "transitions": {
                "": {
                    "targets": [
                        "exists"
                    ],
                    "permissions": {
                        "group": "users"
                    }
                }
            },
            "block": {
                "inputs": {
                    "item": [

                    ]
                },
                "outputs": {
                    "id": "return_value"
                }
            }
        },
        "edit": {
            "description": "Modify item",
            "transitions": {
                "exists": {
                    "targets": [
                        "exists"
                    ],
                    "permissions": {
                        "owner": true
                    }
                }
            },
            "block": {
                "inputs": {
                    "id": [

                    ],
                    "item": [

                    ]
                },
                "outputs": {
                    "id": "id"
                }
            }
        },
        "delete": {
            "description": "Destroy item",
            "transitions": {
                "exists": {
                    "targets": [
                        ""
                    ],
                    "permissions": {
                        "owner": true
                    }
                }
            },
            "block": {
                "inputs": {
                    "id": [

                    ]
                },
                "outputs": [

                ]
            }
        }
    }
}

