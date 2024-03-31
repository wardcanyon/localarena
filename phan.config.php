<?php

use Phan\Config;

/**
 * This configuration will be read and overlaid on top of the
 * default configuration. Command line arguments will be applied
 * after this file is read.
 *
 * @see src/Phan/Config.php
 * See Config for all configurable options.
 *
 * A Note About Paths
 * ==================
 *
 * Files referenced from this file should be defined as
 *
 * ```
 *   Config::projectPath('relative_path/to/file')
 * ```
 *
 * where the relative path is relative to the root of the
 * project which is defined as either the working directory
 * of the phan executable or a path passed in via the CLI
 * '-d' flag.
 */
return [
    // If true, missing properties will be created when
    // they are first seen. If false, we'll report an
    // error message.
    "allow_missing_properties" => true,

    // Allow null to be cast as any type and for any
    // type to be cast to null.
    "null_casts_as_any_type" => true,

    // Backwards Compatibility Checking
    'backward_compatibility_checks' => false,

    // Run a quick version of checks that takes less
    // time
    "quick_mode" => true,

    // Only emit critical issues to start with
    // (0 is low severity, 5 is normal severity, 10 is critical)
    "minimum_severity" => 5,

    // A list of directories that should be parsed for class and
    // method information. After excluding the directories
    // defined in exclude_analysis_directory_list, the remaining
    // files will be statically analyzed for errors.
    //
    // Thus, both first-party and third-party code being used by
    // your application should be included in this list.
    'directory_list' => [
         // Change this to include the folders you wish to analyze
         // (and the folders of their dependencies)
         'src',
         // To speed up analysis, we recommend going back later and
         // limiting this to only the vendor/ subdirectories your
         // project depends on.
         // `phan --init` will generate a list of folders for you
         'src/vendor',
    ],

    // A list of directories holding code that we want
    // to parse, but not analyze
    "exclude_analysis_directory_list" => [
        'src/vendor',
        'src/dojox/analytics/logger',
        // XXX: Tidy this up once we have the games in a subdirectory.
        'src/burglebrostwo',
        'src/thecrew',
        'src/hearts',
        'src/reversi',
        'src/emppty',
    ],
];
