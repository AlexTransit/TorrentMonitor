<?php
include_once('rutracker.org.engine.php');

class rutrackerSearch extends rutracker
{
	//ищем темы пользователя
	public static function mainSearch($params)
	{
    	extract($params);
		$cookie = Database::getCookie($tracker);
		if (rutracker::checkCookie($cookie))
		{
			rutracker::$sess_cookie = $cookie;
			//запускам процесс выполнения
			rutracker::$exucution = TRUE;
		}
		else
    		rutracker::getCookie($tracker);

		if (rutracker::$exucution)
		{
    		$user = iconv('utf-8', 'windows-1251', $name);
    		$page = Sys::getUrlContent(
            	array(
            		'type'           => 'POST',
            		'header'         => 1,
            		'returntransfer' => 1,
            		'url'            => 'https://rutracker.org/forum/tracker.php',
            		'cookie'         => rutracker::$sess_cookie,
            		'postfields'     => 'prev_new=0&prev_oop=0&f%5B%5D=-1&o=1&s=2&tm=-1&pn='.$user.'&nm=',
            		'convert'        => array('windows-1251', 'utf-8//IGNORE'),
            	)
	        );
            $page = str_replace("\t", '', $page);

	        if ( ! empty($page))
	        {
	        	//сбрасываем варнинг
				Database::clearWarnings($tracker);

	    		preg_match_all('/<a class=\"gen f ts-text\" href=\"tracker\.php\?f=\d{1,9}\">(.*)<\/a>/', $page, $section);
	    		preg_match_all('/<a data-topic_id=\"\d{3,9}\" class=\"med tLink ts-text hl-tags bold\" href=\"viewtopic\.php\?t=(\d{3,9})\">(.*)<\/a>/', $page, $threme);
	    		preg_match_all('/<td class=\"row4 small nowrap\" style=\".*\" data-ts_text=\".*\">\n<p>(.*)<\/p>\n(<p>(.*)<\/p>)?<\/td>/', $page, $dates);
	    		
	    		var_dump($dates[1]);
	    		
                if (count($section[1]) == count($threme[1]) && count($threme[1]) == count($dates[1]))
                {
	    		    for ($i=0; $i<count($threme[1]); $i++)
	    		    {
    	    		    $arr = preg_split('/-/', $dates[1][$i]);
    	    		    if (strlen($arr[0]) == 1)
    	    		        $day = '0'.$arr[0];
                        else
                            $day = $arr[0];
    	    		    $date = '20'.$arr[2].'-'.Sys::dateStringToNum($arr[1]).'-'.$day;
	    			    Database::addThremeToBuffer($id, $section[1][$i], $threme[1][$i], $threme[2][$i], $date, $tracker);
	    			}
                }
                else
                    echo 'Not match count';
	    	}
	    	else
				Errors::setWarnings($tracker, 'cant_get_forum_page');

    		$toDownload = Database::takeToDownload($tracker);
    		if ($toDownload != NULL)
    		{
        		if (count($toDownload) > 0)
        		{
                    for ($i=0; $i<count($toDownload); $i++)
                    {
                    	//сбрасываем варнинг
    					Database::clearWarnings($tracker);
                        //сохраняем торрент в файл
                        $id = $toDownload[$i]['id'];
                        $torrent_id = $toDownload[$i]['threme_id'];
                        $name = $toDownload[$i]['threme'];
                        $torrent = Sys::getUrlContent(
                        	array(
                        		'type'           => 'POST',
                        		'returntransfer' => 1,
                        		'url'            => 'https://rutracker.org/forum/dl.php?t='.$torrent_id,
                        		'cookie'         => rutracker::$sess_cookie.'; bb_dl='.$torrent_id,
                        		'sendHeader'     => array('Host' => 'rutracker.org', 'Content-length' => strlen(rutracker::$sess_cookie)),
                        		'referer'        => 'https://rutracker.org/forum/dl.php?t='.$torrent_id,
                        	)
                        );
                        
                        if (Sys::checkTorrentFile($torrent))
                        {
            				$message = $toDownload[$i]['threme'].' добавлена для скачивания.';
            				$status = Sys::saveTorrent($tracker, $toDownload[$i]['threme_id'], $torrent, $toDownload[$i]['threme_id'], 0, $message, date('d M Y H:i'), $name);
        								
            				//обновляем время регистрации торрента в базе
            				Database::setDownloaded($toDownload[$i]['id']);
                        }
                        else
                            Errors::setWarnings($tracker, 'torrent_file_fail');
                    }
                }
            }
        }
	}
}
?>