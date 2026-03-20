<?php

declare (strict_types=1);
/*
 * This file is part of the Stomp package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Stomp\Util;

use RuntimeException;
/**
 * IdGenerator generates Ids which are unique during the runtime scope.
 *
 * @package Stomp\Util
 * @author Jens Radtke <swefl.oss@fin-sn.de>
 */
class Id_Generator
{
    /**
     * @var array
     */
    private static $generated_ids = [];
    /**
     * Generate a not used id.
     *
     * @return int
     */
    public static function generate_id()
    {
        while ($rand = random_int(1, PHP_INT_MAX)) {
            if (!in_array($rand, self::$generated_ids, true)) {
                self::$generated_ids[] = $rand;
                return $rand;
            }
        }
        // This is never hit because the above becomes an infinite loop. Possibly need a release valve.
        // throw new RuntimeException('Message Id generation failed.');
    }
    /**
     * Removes a previous generated id from currently used ids.
     *
     * @param int $generatedId
     */
    public static function release_id($generated_id): void
    {
        $index = array_search($generated_id, self::$generated_ids, true);
        if ($index !== false) {
            unset(self::$generated_ids[$index]);
        }
    }
}