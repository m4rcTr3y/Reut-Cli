<?php
declare(strict_types=1);

namespace Reut\Models;

use Reut\DB\DataBase;
use Reut\DB\Types\Integer;
use Reut\DB\Types\Text;
use Reut\DB\Types\Varchar;

class PostsTable extends DataBase
{
    public function __construct(array $config)
    {
        // Initialize the table shell; relationships will be inferred automatically.
        parent::__construct(
            $config,
            [],
            'Posts',
            false,
            0,
            [],
            []
        );

        // Primary identifier.
        $this->addColumn('id', new Integer(
            false,
            true,
            true
        ));

        // Core post payload.
        $this->addColumn('title', new Varchar(
            150,
            false
        ));

        $this->addColumn('body', new Text(
            false
        ));

        // Link a post back to the author in UsersTable.
        $this->addColumn('user_id', new Integer(
            false
        ));

        // Define the relationship so migrations + viewer know the dependency.
        $this->addForeignKey(
            'user_id',
            'Users',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }
}

