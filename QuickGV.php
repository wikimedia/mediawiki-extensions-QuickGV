<?php
$wgExtensionCredits['parserhook'][] = array(
    'path'           => __FILE__,
    'name'           => 'QuickGV',
    'author'         => '[https://github.com/virus-warnning 吳政璋]',
    'url'            => 'https://www.mediawiki.org/wiki/Extension:QuickGV',
    'descriptionmsg' => 'quickgv-desc',
    'version'        => '0.2.4',
);

$wgAutoloadClasses['QuickGV']        = __DIR__ . '/QuickGV.body.php';
$wgExtensionMessagesFiles['QuickGV'] = __DIR__ . '/QuickGV.i18n.php';
$wgHooks['ParserFirstCallInit'][]    = 'QuickGV::init';
