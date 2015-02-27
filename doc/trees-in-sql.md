Trees in SQL
============

FlupdoCrudMachine has basic support for herarchical data stored in SQL
database. It is intended for handling smaller trees, suitable for categories,
user roles and similar stuff.


Theory
------

Implementation uses Nested Set approach combined with reference to parent,
where reference to parent is primary information and nested sets are
calculated.

See [Nested set model](https://en.wikipedia.org/wiki/Nested_set_model).


Configuration
-------------

Implementation requires specifying names of columns used for indices describing
tree structure.

The minimal SQL table to implement the tree is this:

    CREATE TABLE `tree_node` (
      `id` int(11) NOT NULL AUTO_INCREMENT,     -- given by user
      `parent_id` int(11) DEFAULT NULL,         -- given by user; root node has NULL here
      `tree_left` int(11) DEFAULT NULL,         -- calculated
      `tree_right` int(11) DEFAULT NULL,        -- calculated
      `tree_depth` int(11) DEFAULT NULL,        -- calculated

      -- some additional columns go here ...

      -- primary key and index to quickly find a subtree
      PRIMARY KEY (`id`),
      KEY `tree_left_tree_right` (`tree_left`,`tree_right`),

      -- foreign key to parent node
      CONSTRAINT `parent_ibfk_1` FOREIGN KEY (`parent_id`)
          REFERENCES `tree_node` (`id`) ON UPDATE CASCADE
    );

The default configuration is set for this structure, when different column
names are used, they must be specified in state machine configuration:

    {
        "class": "Smalldb\\StateMachine\\FlupdoCrudMachine",
        "table": "tree_node",
        "nested_sets": {
            "enabled": true,
            "table_columns": {
                "id": "id",
                "parent_id": "parent_page_id",
                "left": "tree_left",
                "right": "tree_right",
                "depth": "tree_depth"
            },
            "order_by": "id"
        },
        ...
    }

Order of nodes in tree can be set using `order_by` option, which contains
ORDER BY clause (SQL expression).


Implementation notes
--------------------

The `tree_left` and `tree_right` columns are used to quickly select whole
subtree and arrange nodes in a correct order. The `tree_depth` may be used
while rendering the tree to correctly indent each node.

The `parent_id` column is the primary information, all `tree_*` columns are
calculated from it.

The whole tree is rebuilt on every update, to ensure consistency. This approach
is not suitable for very large trees, however, it is very simple and reliable.


Usage - Selects
---------------

To select a subtree, you need to know `tree_left` and `tree_right` of the
parent, then you can recursively select all its children using simple select:

    SELECT *
    FROM tree_node
    WHERE tree_left BETWEEN :parent_left AND :parent_right
    ORDER BY tree_left

Or if you have only `parent_id`, you can use simple and fast subselect:

    SELECT *
    FROM tree_node
    WHERE tree_left
        BETWEEN (SELECT tree_left  FROM tree_node WHERE id = :parent_id)
        AND     (SELECT tree_right FROM tree_node WHERE id = :parent_id)
    ORDER BY tree_left


Usage - Updates
---------------

To recalculate whole tree (no partial updates supported) simply call
`FlupdoCrudMachine::recalculateTree()` at the end of your transition handling
method. That's all.


