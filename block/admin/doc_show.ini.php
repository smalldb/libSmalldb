;<?php exit(); __HALT_COMPILER; ?>


[outputs]
title[] = "describe:type"
done[] = "describe:done"

[block:header_item]
.block = "core/out/header"
.x = 261
.y = 0
level = "2"
text = "Smalldb machine: {machine_type}"
slot_weight = "30"
machine_type[] = "describe:type"

[block:show_diagram]
.block = "smalldb/show_diagram"
.x = 261
.y = 207
machine_type[] = "describe:type"
slot_weight = 40

[block:describe]
.block = "smalldb/describe_machine"
.x = 0
.y = 114
type[] = "admin:machine_type"

[block:show_properties]
.block = "smalldb/show_properties"
.x = 262
.y = 352
desc[] = "describe:desc"
slot_weight = 70


; vim:filetype=dosini:
