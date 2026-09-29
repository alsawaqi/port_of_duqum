<?php

// Arabic translations live in custom_lang.php. Use readable English for keys
// not translated yet, while retaining Arabic formatting and editor resources.
$lang = require __DIR__ . '/../english/default_lang.php';
$lang['language_locale'] = 'ar';
$lang['language_locale_long'] = 'ar-AR';
$lang['text_direction'] = 'rtl';

return $lang;
