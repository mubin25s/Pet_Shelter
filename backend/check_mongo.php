<?php
if (extension_loaded('mongodb')) {
    echo "MongoDB Extension Loaded!";
} else {
    echo "MongoDB Extension NOT Loaded. You need specifically install 'php_mongodb.dll' and enable it in php.ini.";
    echo "\n\nCurrent Loaded Extensions:\n";
    print_r(get_loaded_extensions());
}
?>
