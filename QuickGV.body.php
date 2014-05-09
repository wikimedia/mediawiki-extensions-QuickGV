<?php
/**
 * Graphviz 快速製圖器
 *
 * @since  0.1.0
 * @author Raymond Wu https://github.com/virus-warnning
 */
class QuickGV {

	/* 錯誤訊息暫存區 */
	private static $errmsgs = array();

	/**
	 * 掛載點設定 (由 MediaWiki 觸發)
	 *
	 * @since 0.1.0
	 * @param $parser MediaWiki 的語法處理器
	 */
	public static function init(&$parser) {
		$parser->setHook('quickgv', array('QuickGV', 'render'));
		return true;
	}

	/**
	 * 製圖 (由 MediaWiki 觸發)
	 *
	 * @since 0.1.0
	 * @param $in     MediaWiki 寫的語法內文
	 * @param $param  標籤內的參數
	 * @param $parser MediaWiki 的語法處理器
	 * @param $frame  不知道是啥小
	 */
	public static function render($in, $param=array(), $parser=null, $frame=false) {
		global $IP, $wgScriptPath;

		// 計時開始，效能計算用
		$beg_time = microtime(true);

		// TODO: 參數檢查

		// TODO: in 上限管制

		if (isset($param['name'])) $gname = trim($param['name']);
		if (!isset($gname) || $gname=='') $gname = 'G';

		if (isset($param['showmeta']) && $param['showmeta']==='true') {
			$showmeta = true;
		} else {
			$showmeta = false;
		}

		if (isset($param['showdot']) && $param['showdot']==='true') {
			$showdot = true;
		} else {
			$showdot = false;
		}

		$prefix = $parser->mTitle;
		$prefix = str_replace(array('\\','/',' '), '_', $prefix);

		$imgdir = sprintf('%s/images/quickgv', $IP);
		if (!is_dir($imgdir)) mkdir($imgdir);

		$fn = sprintf('%s-%s', $prefix, $gname);
		$infile  = sprintf('%s/images/quickgv/%s.in' , $IP, $fn);
		$dotfile = sprintf('%s/images/quickgv/%s.dot', $IP, $fn);
		$svgfile = sprintf('%s/images/quickgv/%s.svg', $IP, $fn);
		$svgurl  = sprintf('%s/images/quickgv/%s.svg', $wgScriptPath, $fn);

		// in 處理
		file_put_contents($infile, trim($in));

		// 產生 dot 語法
		$dotexec = __DIR__ . '/QuickGV.template.php';
		$cmd = sprintf(
			'php %s %s < %s > %s',
			escapeshellarg($dotexec), // $argv[0]
			escapeshellarg($gname),   // $argv[1]
			escapeshellarg($infile),  // stdin
			escapeshellarg($dotfile)  // stdout
		);
		system($cmd);
		unlink($infile);

		// 執行 dot，產生 svg 圖檔
		$errfile = tempnam($imgdir,'stderr-');
		$cmd = sprintf('dot -Tsvg %s > %s 2> %s',
			escapeshellarg($dotfile), // stdin
			escapeshellarg($svgfile), // stdout
			escapeshellarg($errfile)  // stderr
		);
		system($cmd, $status);

		// dot 指令的錯誤處理
		if ($status!=0) $errstr = file_get_contents($errfile);
		unlink($errfile);
		if ($status!=0) {
			$html = self::showError($errstr);
			$html .= sprintf('<pre>%s</pre>', file_get_contents($dotfile));
			return $html;
		}

		// 輸出
		$html = sprintf('<p><img src="%s?t=%d" style="border:1px solid #777;" /></p>', $svgurl, time());

		if ($showmeta) {
			$elapsed = microtime(true) - $beg_time;

			// 取 Graphviz 版本資訊 (需要獨立 function)
			$verstr = system('dot -V 2>&1');
			$verpos = strpos($verstr,'version')+8;
			$verstr = substr($verstr,$verpos);

			// 取人性化的檔案大小 (需要獨立 function)
			$unit_lv = 0;
			$size = filesize($svgfile);
			while ($size>=1024 && $unit_lv<=2) {
				$size /= 1024;
				$unit_lv++;
			}
			$unit_ch = array('B','KB','MB');

			$table_html = array();
			$table_html[] = sprintf('<tr><th>%s</th><td style="text-align:left;">%s</td></tr>', wfMessage('filepath'), $svgurl);
			$table_html[] = sprintf('<tr><th>%s</th><td style="text-align:left;">%.2f %s</td></tr>', wfMessage('filesize')->plain(), $size, $unit_ch[$unit_lv]);
			$table_html[] = sprintf('<tr><th>%s</th><td style="text-align:left;">%.3f %s</td></tr>', wfMessage('exectime')->plain(), $elapsed, wfMessage('seconds')->plain());
			$table_html[] = sprintf('<tr><th>%s</th><td style="text-align:left;">%s</td></tr>', wfMessage('graphviz-ver')->plain(), $verstr);
			$table_html = implode("\n", $table_html);
			$table_html = sprintf('<table class="mw_metadata" style="margin-left:0; margin-top:5px;"><tbody>%s</tbody></table>',$table_html);
			$html .= $table_html;
			unset($table_html);
		}

		if ($showdot) {
			$html .= sprintf('<pre>%s</pre>', file_get_contents($dotfile));
		}

		// 移除暫時性的 dot
		unlink($dotfile);

		return $html;
	}

	/**
	 * 增加錯誤訊息
	 *
	 * @since 0.1.1
	 * @param $msg 錯誤訊息
	 */
	public static function addError($msg) {
		self::$errmsgs[] = $msg;
	}

	/**
	 * 顯示錯誤訊息
	 *
	 * @since 0.1.1
	 * @param $msg 錯誤訊息，如果沒有提供，會使用 addError 增加的錯誤訊息
	 */
	public static function showError($msg='') {
		// 內建 CSS:
		// .errorbox   - MW 顯示錯誤訊息的 CSS class
		// .warningbox - MW 顯示警示訊息的 CSS class
		// 這兩個都有 float: left; 用完後需要 clear 一下
		// 這些 CSS 真是夠醜的，以後要修一下

		if ($msg==='') {
			if (count(self::$errmsgs)>0) {
				foreach (self::$errmsgs as $cached_msg) {
					$html .= "<p>$cached_msg</p>";
				}
			} else {
				$html = "<p>Test</p>";
			}
		} else {
			$html = "<p>$msg</p>";
		}

		$html = sprintf('<div class="errorbox" style="margin:0;">%s</div>',$html);
		$html .= '<div style="clear:both;"></div>';
		return $html;
	}

	/**
	 * 檢查參數
	 */
	public static function validateParam() {

	}

	/**
	 * 檢查環境
	 */
	public static function validateRequirements() {

	}

}
