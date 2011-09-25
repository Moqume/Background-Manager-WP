<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Database;

use Pf4wp\Database\SimpleSchema;

/**
 * Implements a simple schema for Galleres
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Database
 */
class Galleries extends SimpleSchema
{
    protected static $schema = array(
        "CREATE TABLE IF NOT EXISTS `{#prefix#}bgm_galleries` (
          `id` BIGINT(20) NOT NULL ,
          `name` VARCHAR(45) NOT NULL ,
          `description` TINYTEXT NULL DEFAULT '' ,
          `css` TEXT NULL DEFAULT '' ,
          `trash` TINYINT(1)  NULL DEFAULT 0 ,
          PRIMARY KEY (`id`) ,
          INDEX `idx_trash` (`trash` ASC) ) {#charset_collate#}",
    );
}