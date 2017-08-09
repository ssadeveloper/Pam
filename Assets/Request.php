<?php

namespace Pam\Assets;

use Pam\Model;

class Request extends Model
{
    protected $tableName = 'asset_requests';

    protected $idColumn = 'asset_mr_id';
}