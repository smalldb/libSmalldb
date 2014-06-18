{
    "_": "<?php printf('_%c%c}%c',34,10,10);__halt_compiler();?>",
    "description": "Article.",
    "class": "\\Smalldb\\StateMachine\\ArrayMachine",
    "states": {
        "published": {
            "description": ""
        },
        "rejected": {
            "description": ""
        },
        "submitted": {
            "description": ""
        },
        "waiting": {
            "description": ""
        },
        "writing": {
            "description": ""
        }
    },
    "actions": {
        "accept": {
            "transitions": {
                "submitted": {
                    "targets": [
                        "waiting",
                        "published"
                    ]
                }
            }
        },
        "create": {
            "returns": "new_id",
            "transitions": {
                "": {
                    "targets": [
                        "writing"
                    ]
                }
            }
        },
        "edit": {
            "transitions": {
                "writing": {
                    "targets": [
                        "writing"
                    ]
                },
                "submitted": {
                    "targets": [
                        "submitted"
                    ]
                }
            }
        },
        "submit": {
            "transitions": {
                "writing": {
                    "targets": [
                        "submitted"
                    ]
                }
            }
        },
        "return": {
            "transitions": {
                "submitted": {
                    "targets": [
                        "writing"
                    ]
                }
            }
        },
        "withdraw": {
            "transitions": {
                "submitted": {
                    "targets": [
                        "writing"
                    ]
                }
            }
        },
        "publish": {
            "transitions": {
                "waiting": {
                    "targets": [
                        "published"
                    ]
                }
            }
        },
        "hide": {
            "transitions": {
                "published": {
                    "targets": [
                        "submitted"
                    ]
                }
            }
        },
        "reject": {
            "transitions": {
                "submitted": {
                    "targets": [
                        "rejected"
                    ]
                }
            }
        }
    }
}

