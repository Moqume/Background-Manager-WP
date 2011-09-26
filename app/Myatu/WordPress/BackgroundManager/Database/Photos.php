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
 * Implements a simple schema for Photos
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Database
 */
class Photos extends SimpleSchema
{
    protected static $schema = array(
        "CREATE TABLE IF NOT EXISTS `{#prefix#}bgm_photos` (
          `id` BIGINT(20) NOT NULL AUTO_INCREMENT ,
          `bgm_gallery_id` BIGINT(20) NOT NULL ,
          `guid` TINYTEXT NULL DEFAULT '' ,
          `title` TINYTEXT NULL DEFAULT '' ,
          `owner` TINYTEXT NULL DEFAULT '' ,
          `license` TINYTEXT NULL DEFAULT '' ,
          `license_url` TINYTEXT NULL DEFAULT '' ,
          `url` TEXT NULL DEFAULT '' ,
          `thumb` TEXT NULL DEFAULT '' ,
          PRIMARY KEY (`id`) ) {#charset_collate#}",
    );
}