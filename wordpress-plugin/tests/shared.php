<?php
require __DIR__.'/recovery.php';
check(file_exists(dirname(__DIR__).'/includes/class-shared-patterns.php'),'shared pattern tools exist');
require dirname(__DIR__).'/includes/class-shared-patterns.php';
check(WPGPTVibe_Shared_Patterns::references('<!-- wp:block {"ref":42} /-->',42),'shared pattern reference detected');
check(!WPGPTVibe_Shared_Patterns::references('<!-- wp:block {"ref":142} /-->',42),'reference scan matches exact pattern ID');
$typography=WPGPTVibe_Patterns::attributes('wpgptvibe/text',['fontSize'=>20,'headingSize'=>36]);
check($typography['fontSize']===20&&$typography['headingSize']===36,'visual typography controls accept safe sizes');
rejects(fn()=>WPGPTVibe_Patterns::attributes('wpgptvibe/text',['fontSize'=>-1]),'out of range');
