Example: core.json.php configuration
====================================

.

    {
        "block_storage": {
            "smalldb": {
                "storage_class": "Smalldb\\Cascade\\BlockStorage",
                "storage_weight": 20,
                "alias": "smalldb",
                "backend_class": "Smalldb\\StateMachine\\FlupdoBackend",
                "resources": {
                    "flupdo": "database"
                }
            }
        },
        "context": {
            "resources": {
                "database": {
                    "factory": [ "Smalldb\\Flupdo\\Flupdo", "createInstanceFromConfig" ],
                    "driver": "mysql",
                    "database": "...",
                    "host": "localhost",
                    "username": "...",
                    "password": "..."
                }
            }
        }
    }

