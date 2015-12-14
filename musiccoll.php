<?php
// need metaflac (package flac) to get flactags
// need shntool, shnsplit, shnconv (package shntool) to get flac info and split file
// need cuebreakpoints, cuetag (package cuetools) to get cue info and tag file
// need ffmpeg to conver ape to wav

define ('script_name', 'Music Collectioner v1.0');
define ('library', '/Users/aslok/Downloads/');

// for last.fm api
define ('api_url', 'http://ws.audioscrobbler.com/2.0/');
define ('api_key', 'b16d54e8e368c7b116799d1611d86ae8');

// $ iconv --list
define ('cue_encoding', 'CP1251');
//define ('cue_encoding', 'UTF-8');
//define ('cue_encoding', 'ISO-8859-1');


define ('N', "\n");
define ('T', "\t");
define ('R', "\r");

print ('[ ' . script_name . ' ]' . N . N);

// current dir (in use if not defined custom)
$pwd = getcwd();

// $path = dirname(__FILE__);

// for function check_args
$allowed_args = array ('/^-h$/', '/^-v$/', '/^-vv$/', '/^-r$/', '/^-f$/', '/^-ro$/', '/^-nl$/', '/^-tl$/', '/^-sf$/', '/^-st$/', '/^[\w\/~\.а-я].*$/iu');
// for function search_ext
$dany_folders = array ('.', '..');
// for function search name on last.fm
$hide_realese_info = 'remaster|антология';


$args = get_args();
check_args();

$target = isset ($args['path']) ? $args['path'] : $pwd;
$recurs = isset ($args['-r']);

printvv(N . 'Путь к папке: ' . $target . N . N);
printvv(N . 'Рекурсивно: ' . ($recurs ? 'Да' : 'Нет') . N . N);

$apes = search_ext('ape', $target, $recurs);

printvv('Найдены файлы:' . N);
var_dump_vv($apes);

foreach ($apes as $ape_file)
{
	printv(N);
	printv('Обрабатываем файл: ' . $ape_file . N);
	/*if (($cue = search_cue($ape_file)))
	{
		printv(N);
		printv('Найден файл: ' . $cue . N);*/
		if (!isset ($args['-ro']))
		{
			ape2flac($ape_file);
		}
		else
		{
			printv('Перекодировка файла альбома не будет выполнена (режим ro)' . N);
		}
	/*}*/
}

$wvs = search_ext('wv', $target, $recurs);

printvv('Найдены файлы:' . N);
var_dump_vv($wvs);

foreach ($wvs as $wv_file)
{
	printv(N);
	printv('Обрабатываем файл: ' . $wv_file . N);
	if (!isset ($args['-ro']))
	{
		wv2flac($wv_file);
	}
	else
	{
		printv('Перекодировка файла альбома не будет выполнена (режим ro)' . N);
	}
}


if (isset ($args['-sf']))
{
	printv('Поиск файлов flac не будет выполнен (режим sf)' . N);
	exit;
}


$flacs = search_ext('flac', $target, $recurs);

printvv(N);
printvv('Найдены файлы:' . N);
var_dump_vv($flacs);

if (isset ($args['-tl']) && count($flacs) && ($txt = search_txt(reset($flacs))))
{
	$titles = file($txt);
	$titles_key = 0;
}

foreach ($flacs as $flac_file)
{
	printv(N);
	printv('Обрабатываем файл: ' . $flac_file . N);
	var_dump_vv(get_flac_tags($flac_file));
	if (isset ($args['-tl']))
	{
		if (!$txt)
		{
			print('Ошибка! При обработке файла: ' . $flac_file . N);
			print('Не найден файл с описанием заголовка' . N);
		}
		if (!isset ($titles[$titles_key]) || !($title = trim($titles[$titles_key++])))
		{
			print('Ошибка! При обработке файла: ' . $flac_file . N);
			print('Не найден соответствующий заголовк' . N);
			break;
		}
		printv('Новое название: ' . $title . N);
		if (!isset ($args['-ro']) && !isset ($args['-st']))
		{
			exec_program(	'metaflac --set-tag="TITLE=' . $title . '" ' . escapeshellarg($flac_file));
		}
		elseif (isset ($args['-st']))
		{
			printv('Заканчиваем выполнение задач по дайнному файлу (режим st)' . N);
		}
		else
		{
			printv('Заканчиваем выполнение задач по дайнному файлу (режим ro)' . N);
		}
		continue;
	}
	/*return (array (	'artist' => isset ($tags['ARTIST']) ? $tags['ARTIST'] : '',
			'album' => isset ($tags['ALBUM']) ? $tags['ALBUM'] : '',
			'title' => isset ($tags['TITLE']) ? $tags['TITLE'] : '',
			'year' => isset ($tags['DATE']) ? $tags['DATE'] : '',
			'track' => isset ($tags['TRACKNUMBER']) ? $tags['TRACKNUMBER'] : '',
			'genre' => isset ($tags['GENRE']) ? $tags['GENRE'] : '',
			'total' => isset ($tags['TRACKTOTAL']) ? $tags['TRACKTOTAL'] : '',
			'comment' => isset ($tags['COMMENT']) ? $tags['COMMENT'] : ''));*/
	if (($cue = search_cue($flac_file)))
	{
		printv(N);
		printv('Найден файл: ' . $cue . N);
		$flac_file_length = mmss2s(get_file_length($flac_file));
		$cue_info = get_cue_info($cue, $flac_file_length);
		var_dump_vv($cue_info);

		printv('Артист: ' . $cue_info['artist'] . N);
		printv('Альбом: ' . $cue_info['year'] . ' - ' . $cue_info['album'] . N);
		printv('Диск: ' . $cue_info['disc'] . N);
		printv('Жанр: ' . $cue_info['genre'] . N);
		printv('Треков: ' . $cue_info['total'] . N);
		foreach ($cue_info['tracks'] as $cue_track)
		{
			printv($cue_track['track'] . ' - ' . $cue_track['title'] . ' (' . s2mmss($cue_track['length']) . ')' . N);
		}

		if (!isset ($args['-ro']) && $cue_info['total'] && $cue_info['total'] > 1)
		{
			split_cueflac($flac_file, $cue);
		}
		elseif ($cue_info['total'] && $cue_info['total'] === 1)
		{
			$split_tracks = array ($flac_file);
		}
		elseif (isset ($args['-ro']))
		{
			printv('Возможны ошибки проверки, нарезка файла альбома не будет выполнена (режим ro)' . N);
		}

		if ($cue_info['total'] && $cue_info['total'] > 1 && !($split_tracks = check_split_tracks($flac_file, $cue_info)))
		{
			print('Ошибка! При обработке файла: ' . $flac_file . N);
			print('Длительность треков или их колличество не соответствует данным в cue: ' . $cue . N);
			if (!isset ($args['-ro']))
			{
				continue;
			}
			printv('Продолжаем обработку файла (режим ro)' . N);
		}
		elseif ($cue_info['total'] === 1)
		{
			printv('Нарезка не требуется, альбом из одного трека. Продолжаем обработку файла' . N);
		}

		$cue_info['album'] = short_realese($cue_info['album']);
		$artist = get_search_artist($cue_info['artist']);
		$album = get_search_album($cue_info['album'], $cue_info['artist']);
		if (!$album['title'] && !isset ($args['-nl']))
		{
			$album = get_search_album($cue_info['album'], $artist['title']);
		}
		if (!$artist['title'] && !isset ($args['-nl']))
		{
			printv('Исполнитель "' . $cue_info['artist'] . '" не найден на last.fm' . N);
		}
		elseif ($artist['title'] != $cue_info['artist'] && !isset ($args['-nl']))
		{
			printv('Имя исполнителя "' . $artist['title'] . '" будет использовано вместо "' . $cue_info['artist'] . '"  (найден на last.fm)' . N);
		}
		$image_file = '';
		if (!isset ($args['-ro']) && ($image = search_img($flac_file)))
		{
			$image_file = dirname(reset($split_tracks)) . '/cover.jpg';
			file_put_contents($image_file, file_get_contents($image));
		}
		if (!$album['title'] && !isset ($args['-nl']))
		{
			printv('Альбом "' . $cue_info['album'] . '" исполнителя "' . $cue_info['artist'] . '" не найден на last.fm' . N);
			if (isset ($args['-ro']))
			{
				printv('Заканчиваем выполнение задач по дайнному файлу (режим ro)' . N);
				continue;
			}
		}
		else
		{
			printv('Кандидат в альбомы "' . $album['title'] . '" (найден на last.fm)' . N);
			if ($album['mbid'])
			{
				printvv('Альбом имеет метку MusicBrainz: ' . $album['mbid'] . N);
				$album_tracks = get_album($album['mbid']);
				var_dump_vv($album_tracks);
				if ($album_tracks['total'] != $cue_info['total'])
				{
					printv(	'Ошибка! Длительность треков полученных с last.fm: ' . $album['url'] .
						' или их колличество не соответствует данным в cue (' . $cue_info['total'] . ' != ' . $album_tracks['total'] . ')' . N);
					$album = array ('title' => '',
							'url' => '',
							'mbid' => '',
							'image' => '');
				}
			}
			if ($album['title'] && $album['title'] != $cue_info['album'])
			{
				printv('Название альбома "' . $album['title'] . '" будет использовано вместо "' . $cue_info['album'] . '" (найден на last.fm)' . N);
			}
			if (isset ($args['-ro']))
			{
				printv('Заканчиваем выполнение задач по дайнному файлу (режим ro)' . N);
				continue;
			}
			if (!$image_file && ($image = $album['image']))
			{
				$image_file = dirname(reset($split_tracks)) . '/cover.jpg';
				file_put_contents($image_file, file_get_contents($image));
			}
		}

		if (isset ($args['-st']))
		{
			printv('Заканчиваем выполнение задач по дайнному файлу (режим st)' . N);
			continue;
		}

		$track_key = 0;
		$split_tracks_dirname = dirname(reset($split_tracks)) . '/';
		foreach ($split_tracks as $split_track)
		{
			set_tags(	$split_track,
					$artist['title'] ? $artist['title'] : $cue_info['artist'],
					$album['title'] ? $album['title'] : $cue_info['album'],
					$cue_info['year'],
					$cue_info['tracks'][$track_key]['title'],
					$cue_info['tracks'][$track_key]['track'],
					$cue_info['total'],
					$cue_info['genre'],
					$cue_info['comment'],
					$image_file,
					$cue_info['disc'],
					$cue_info['total']);
			$new_split_track_name = $split_tracks_dirname .
						sprintf("%02d", $cue_info['tracks'][$track_key]['track']) . ' - '.
						$cue_info['tracks'][$track_key]['title'] . '.flac';
			rename($split_track, $new_split_track_name);
			$track_key++;
		}
		$artist_dirname = library . ($artist['title'] ? $artist['title'] : $cue_info['artist']);
		$new_split_track_dirname = 	$artist_dirname . '/' .
						$cue_info['year'] . ' - ' .
						($album['title'] ? $album['title'] : $cue_info['album']) .
						((isset ($album['disc']) && $album['disc'] ? $album['disc'] : $cue_info['disc']) !== '' ?
							(' (CD' . ($album['disc'] ? $album['disc'] : $cue_info['disc']) . ')') :
							'');
		if (!file_exists($artist_dirname))
		{
			printv('Создаем каталог для альбомов исполнителя: ' . $artist_dirname . N);
			mkdir($artist_dirname);
		}
		if (file_exists($new_split_track_dirname))
		{
			if (isset ($args['-f']))
			{
				rrmdir($new_split_track_dirname);
			}
			else
			{
				print('Ошибка! При обработке файла: ' . $flac_file . N);
				print('Найден существующий каталог альбома исполнителя: ' . $new_split_track_dirname . N);
				continue;
			}
		}
		printv('Перемещаем треки: ' . $new_split_track_dirname . N);
		rename($split_tracks_dirname, $new_split_track_dirname);
	}
}



































function set_tags(	$flac_file, $artist = '', $album = '', $year = '', $title = '', $track = '', $tracktotal = '', $genre = '', $comment = '', $image_file = '',
			$discnumber = '', $totaltracks = '', $albumartist ='', $originaldate = '',
			$musicbrainz_artistid = '', $musicbrainz_albumartistid = '', $musicbrainz_albumid = '', $musicbrainz_trackid = '', $musicip_puid = '', $asin = '',
			$releasecountry = '', $releasetype = '', $releasestatus = '', $language = '',
			$albumartistsort = '', $artistsort = '', $script = '')
{
	$img_import = '';
	if ($image_file)
	{
		$img_import = '--import-picture-from=' . escapeshellarg($image_file);
	}

	$ext_tags = '';
	if ($discnumber !== '') $ext_tags .= ' --set-tag="discnumber=' . $discnumber . '"';
	if ($totaltracks !== '') $ext_tags .= ' --set-tag="totaltracks=' . $totaltracks . '"';
	if ($albumartist !== '') $ext_tags .= ' --set-tag="albumartist=' . $albumartist . '"';
	if ($originaldate !== '') $ext_tags .= ' --set-tag="originaldate=' . $originaldate . '"';
	if ($musicbrainz_artistid !== '') $ext_tags .= ' --set-tag="musicbrainz_artistid=' . $musicbrainz_artistid . '"';
	if ($musicbrainz_albumartistid !== '') $ext_tags .= ' --set-tag="musicbrainz_albumartistid=' . $musicbrainz_albumartistid . '"';
	if ($musicbrainz_albumid !== '') $ext_tags .= ' --set-tag="musicbrainz_albumid=' . $musicbrainz_albumid . '"';
	if ($musicbrainz_trackid !== '') $ext_tags .= ' --set-tag="musicbrainz_trackid=' . $musicbrainz_trackid . '"';
	if ($musicip_puid !== '') $ext_tags .= ' --set-tag="musicip_puid=' . $musicip_puid . '"';
	if ($asin !== '') $ext_tags .= ' --set-tag="asin=' . $asin . '"';
	if ($albumartistsort !== '') $ext_tags .= ' --set-tag="albumartistsort=' . $albumartistsort . '"';
	if ($artistsort !== '') $ext_tags .= ' --set-tag="artistsort=' . $artistsort . '"';
	$ext_tags .= ' --set-tag="script=' . ($script !== '' ? $script : script_name) . '"';

	exec_program(	'metaflac --remove --block-type=PICTURE ' . escapeshellarg($flac_file) . '; ' .
			'metaflac --remove-all-tags ' . $img_import .
				' --set-tag="ARTIST=' . $artist .
				'" --set-tag="ALBUM=' . $album .
				'" --set-tag="TITLE=' . $title .
				'" --set-tag="DATE=' . $year .
				'" --set-tag="TRACKNUMBER=' . $track .
				'" --set-tag="GENRE=' . $genre .
				'" --set-tag="TRACKTOTAL=' . $tracktotal .
				'" --set-tag="COMMENT=' . $comment .
				'"' . $ext_tags . ' ' . escapeshellarg($flac_file));
}


function check_split_tracks($flac_file, $cue_info)
{
	$dirname = dirname($flac_file) . '/split-tracks/';
	$files = search_ext('flac', $dirname);
	printvv('Колличество треков соответстует: ' . (count ($files) === count ($cue_info['tracks']) ? 'Да' : 'Нет') . N);
	if (count ($files) != count ($cue_info['tracks']))
	{
		printvv('Файлов: ' . count ($files) . N);
		printvv('Описано в cue: ' . count ($cue_info['tracks']) . N);
		return (false);
	}
	foreach ($files as $key => $file)
	{
		$file_length_s = mmss2s(get_file_length($file));
		$cue_track_length_s = $cue_info['tracks'][$key]['length'];
		printvv('Трек ' . $key . ' - разница по длительности ' . abs($file_length_s - $cue_track_length_s) . ' сек' . N);
		if (abs($file_length_s - $cue_track_length_s) > 5)
		{
			return (false);
		}
	}
	return ($files);
}


function utf8_compliant($str)
{
	if (strlen($str) == 0)
	{
		return TRUE;
	}
	return (preg_match('/^.{1}/us',$str,$ar) == 1);
}


function mmss2s($time)
{
	$index = explode(':', $time);
	return (($index[0] * 60) + $index[1]);
}


function s2mmss($time)
{
	$time_mm = $time / 60;
	$time_ss = $time % 60;
	return (sprintf('%02d:%02d', $time_mm, $time_ss));
}


function get_cue_info($cue, $length_s)
{
	$cue_data = file($cue);
	$out = array (	'artist' => '',
			'album' => '',
			'year' => '',
			'total' => '',
			'genre' => '',
			'disc' => '',
			'discid' => '',
			'comment' => '',
			'tracks' => array ());

	$mod = 'album';
	$track_n = 0;

	foreach ($cue_data as $line)
	{
		$line = trim($line);
		if (!utf8_compliant($line))
		{
			$line = iconv (cue_encoding, 'UTF-8', $line);
		}
		if (false !== strpos($line, 'TRACK '))
		{
			$mod = 'track';
			$track = array (	'track' => ++$track_n,
						'title' => '',
						'length' => '',
						'artist' => '');
		}
		if ($mod == 'album')
		{
			if (false !== strpos($line, 'REM '))
			{
				if (false !== ($pos = strpos($line, 'GENRE ')))
				{
					$out['genre'] = trim(substr($line, $pos + strlen('GENRE ')));
					if (0 === strpos($out['genre'], '"'))
					{
						$out['genre'] = substr($out['genre'], 1, -1);
					}
				}
				elseif (false !== ($pos = strpos($line, 'DATE ')))
				{
					$out['year'] = trim(substr($line, $pos + strlen('DATE ')));
				}
				elseif (false !== ($pos = strpos($line, 'DISCID ')))
				{
					$out['discid'] = trim(substr($line, $pos + strlen('DISCID ')));
				}
				elseif (false !== ($pos = strpos($line, 'DISC ')))
				{
					$out['disc'] = trim(substr($line, $pos + strlen('DISC ')));
				}
				elseif (false !== ($pos = strpos($line, 'COMMENT "')))
				{
					$out['comment'] = trim(substr($line, $pos + strlen('COMMENT "'), -1));
				}
			}
			elseif (false !== ($pos = strpos($line, 'PERFORMER "')))
			{
				$out['artist'] = trim(substr($line, $pos + strlen('PERFORMER "'), -1));
			}
			elseif (false !== ($pos = strpos($line, 'TITLE "')))
			{
				$out['album'] = trim(substr($line, $pos + strlen('TITLE "'), -1));
			}
		}
		elseif ($mod == 'track')
		{
			if (false !== ($pos = strpos($line, 'PERFORMER "')))
			{
				$track['artist'] = trim(substr($line, $pos + strlen('PERFORMER "'), -1));
			}
			elseif (false !== ($pos = strpos($line, 'TITLE "')))
			{
				$track['title'] = trim(substr($line, $pos + strlen('TITLE "'), -1));
			}
			elseif (false !== ($pos = strpos($line, 'INDEX ')) && !$track['length'])
			{
				$track['length'] = mmss2s(end(explode(' ', trim(substr($line, $pos + strlen('INDEX '))))));
			}
			$out['tracks'][$track_n - 1] = $track;
		}
	}

	$rev_tracks = array_reverse($out['tracks']);
	$prev_index_s = $length_s;
	foreach ($rev_tracks as $track)
	{
		$out['tracks'][$track['track'] - 1]['length'] = $prev_index_s - $track['length'];
		$prev_index_s = $track['length'];
	}
	$out['total'] = count ($out['tracks']);

	return ($out);
}


function ape2flac($ape_path)
{
	global $args;
	$output = !isset ($args['-v']) && !isset ($args['-vv']) ? ' 2> /dev/null' : '';
	$path_info = pathinfo($ape_path);
	$dirname = $path_info['dirname'] . '/';
	$filename = $path_info['filename'];
	$flac_path = $dirname . $filename . '.flac';
	$wav_path = $dirname . $filename . '.wav';
	if (!file_exists($flac_path))
	{
		if (!file_exists($wav_path))
		{
			$data = exec_program('ffmpeg -i ' . escapeshellarg($ape_path) . ' ' . escapeshellarg($wav_path) . $output);
			printv('Файл сохранен: ' . $wav_path . N);
		}
		else
		{
			printv('Найден файл: ' . $wav_path . N);
		}
		$data = exec_program('flac --delete-input-file --compression-level-8 --best -V ' . escapeshellarg($wav_path) . ' -o ' . escapeshellarg($flac_path) . $output);
		printv('Файл сохранен: ' . $flac_path . N);
		printv('Файл удален: ' . $wav_path . N);
	}
	else
	{
		printv('Найден файл: ' . $flac_path . N);
	}
}


function wv2flac($wv_path)
{
	global $args;
	$output = !isset ($args['-v']) && !isset ($args['-vv']) ? ' 2> /dev/null' : '';
	$path_info = pathinfo($wv_path);
	$dirname = $path_info['dirname'] . '/';
	$filename = $path_info['filename'];
	$flac_path = $dirname . $filename . '.flac';
	$wav_path = $dirname . $filename . '.wav';
	if (!file_exists($flac_path))
	{
		if (!file_exists($wav_path))
		{
			$data = exec_program('wvunpack -cc ' . escapeshellarg($wv_path) . ' -o ' . escapeshellarg($wav_path) . '' . $output);
			printv('Файл сохранен: ' . $wav_path . N);
		}
		else
		{
			printv('Найден файл: ' . $wav_path . N);
		}
		$data = exec_program('flac --delete-input-file --compression-level-8 --best -V ' . escapeshellarg($wav_path) . ' -o ' . escapeshellarg($flac_path) . $output);
		printv('Файл сохранен: ' . $flac_path . N);
		printv('Файл удален: ' . $wav_path . N);
	}
	else
	{
		printv('Найден файл: ' . $flac_path . N);
	}
}


function split_cueflac($path, $cue)
{
	global $args;
	$output = !isset ($args['-v']) && !isset ($args['-vv']) ? ' 2> /dev/null' : '';
	$out_dir = dirname($path) . '/split-tracks/';
	if (!file_exists($out_dir . 'split-track01.flac'))
	{
		if (!file_exists($out_dir))
		{
			mkdir($out_dir);
		}
		$data = exec_program(	'cuebreakpoints --prepend-gaps -i cue ' . escapeshellarg($cue) .
					' 2> /dev/null | shnsplit -o flac -O never -d ' . escapeshellarg($out_dir) . ' ' . escapeshellarg($path) . '' . $output);
		printv('Треки сохранены: ' . $out_dir . N);
	}
	else
	{
		printv('Треки найдены: ' . $out_dir . N);
	}
}


function search_cue($path)
{
	$cue_files = search_ext('cue', dirname($path));

	$path_info = pathinfo($path);
	$cue_name = $path_info['filename'] . '.cue';
	$cue_name_2 = $path_info['basename'] . '.cue';

	foreach ($cue_files as $cue_file)
	{
		if (basename($cue_file) == $cue_name || basename($cue_file) == $cue_name_2)
		{
			printvv('Найден cue данного альбома: ' . $cue_file . N);
			return ($cue_file);
		}
	}
	$cue_file = reset($cue_files);
	if ($cue_file)
	{
		printvv('Имя cue файла не соостветсуву имени файла альбома: ' . $cue_file . N);
	}
	return ($cue_file);
}


function search_img($path)
{
	if (file_exists($cover = dirname($path) . '/cover.jpg'))
	{
		return ($cover);
	}
	if ($cover = reset(search_ext('jpg', dirname($path))))
	{
		return ($cover);
	}
	if ($cover = reset(search_ext('jpg', dirname($path), true)))
	{
		return ($cover);
	}
}


function search_txt($path)
{
	if (file_exists($list = dirname($path) . '/list.txt'))
	{
		return ($list);
	}
}


function get_album($mbid)
{
	global $args;
	if (isset ($args['-nl']))
	{
		printv('Получение информации о альбоме с last.fm не будет выполнено' . N);
		return (false);
	}

	$url = api_url . '?method=album.getinfo&mbid=' . urlencode($mbid) .'&api_key=' . api_key;
	$resp = simplexml_load_file($url);
	$out = array (	'artist' => (string) $resp->album->artist,
			'album' => (string) $resp->album->name,
			'year' => (string) $resp->album->releasedate,
			'total' => (string) count($resp->album->tracks->track),
			'disc' => '',
			'mbid' => (string) $resp->album->mbid,
			'url' => (string) $resp->album->url,
			'tracks' => array ());

	printvv('Всего найдено ' . count($resp->album->tracks->track) . ' треков' . N);
	var_dump_vv($resp);

	$key = 1;
	foreach ($resp->album->tracks->track as $track)
	{
		$out['tracks'][] = array (	'track' => (string) $key,
						'title' => (string) $track->name,
						'lenght' => (string) $track->duration,
						'artist' => (string) $track->artist->name,
						'artistmbid' => (string) $track->artist->mbid,
						'mbid' => (string) $track->mbid,
						'url' => (string) $track->url);
		$key++;
	}
	return($out);
}


function get_file_length($path, $type = 'flac')
{
	$out = '0:0';
	$data = exec_program('shntool len -c -t -i ' . $type . ' ' . escapeshellarg($path));
	if (preg_match_all('/^\s+(\d+:\d+)\.\d+\s+/s', $data, $match))
	{
		$out = $match[1][0];
	}
	return ($out);
}


function get_album_data($path)
{
	$tags = get_flac_tags($path);
	return (array (	'artist' => isset ($tags['ARTIST']) ? $tags['ARTIST'] : '',
			'album' => isset ($tags['ALBUM']) ? $tags['ALBUM'] : '',
			'title' => isset ($tags['TITLE']) ? $tags['TITLE'] : '',
			'year' => isset ($tags['DATE']) ? $tags['DATE'] : '',
			'track' => isset ($tags['TRACKNUMBER']) ? $tags['TRACKNUMBER'] : '',
			'genre' => isset ($tags['GENRE']) ? $tags['GENRE'] : '',
			'disc' => isset ($tags['DISC']) ? $tags['DISC'] : '',
			'total' => isset ($tags['TRACKTOTAL']) ? $tags['TRACKTOTAL'] : '',
			'comment' => isset ($tags['COMMENT']) ? $tags['COMMENT'] : ''));
}


function get_search_artist($name)
{
	global $args;
	if (isset ($args['-nl']))
	{
		printv('Поиск артиста на last.fm не будет выполнен (режим nl)' . N);
		return(	array (	'title' => '',
				'url' => '',
				'mbid' => '',
				'image' => ''));
	}
	$url = api_url . '?method=artist.search&artist=' . urlencode($name) .'&api_key=' . api_key;
	$resp = simplexml_load_file($url);
        $artist = $resp->results->artistmatches->artist[0];
	printvv('Всего найдено ' . count($resp->results->artistmatches->artist) . ' артистов' . N);
	var_dump_vv($artist);
	return($resp ?
			array (	'title' => (string) $artist->name,
				'url' => (string) $artist->url,
				'mbid' => (string) $artist->mbid,
				'image' =>  (string) end($artist->image)) :
			array (	'title' => '',
				'url' => '',
				'mbid' => '',
				'image' => ''));
}


function get_search_album($name, $artist)
{
	global $args;
	$out = array (	'title' => '',
			'disc' => '',
			'url' => '',
			'mbid' => '',
			'image' => '');
	if (isset ($args['-nl']))
	{
		printv('Поиск альбома на last.fm не будет выполнен (режим nl)' . N);
		return($out);
	}
	$url = api_url . '?method=album.search&album=' . urlencode('"' . $artist . '" "' . $name . '"') .'&api_key=' . api_key;
	$resp = simplexml_load_file($url);
	printvv('Всего найдено ' . count($resp->xpath('results/albummatches/album')) . ' альбомов' . N);
	var_dump_vv($resp->xpath('results/albummatches/album[1]'));
	foreach ($resp->xpath('results/albummatches/album') as $album)
	{
		$out = array (	'title' => (string) $album->name,
				'disc' => '',
				'url' => (string) $album->url,
				'mbid' => (string) $album->mbid,
				'image' =>  (string) end($album->xpath('image[last()]')));
		break;
	}
	var_dump_vv($out);
	return($out);
}


function short_realese($name)
{
	global $hide_realese_info;
	if (preg_match('/^(.*)(\s+\([^\)]*(' . $hide_realese_info . ').*)$/iu', $name, $match))
	{
		printv('Поиск: вместо названия альбома "' . $name . '" будет использовать "' . $match[1] . '"' . N);
		$name = $match[1];
	}
	return ($name);
}


function get_flac_tags($path)
{
	$out = array ();
	$data = exec_program('metaflac --list ' . escapeshellarg($path), false);
	if (preg_match_all('/comment\[\d+\]: (\w+)="?([\w\.,\(\)а-я:\s+"\'-]*)"?' . N . '\s?/uis', $data, $match))
	{
		foreach ($match[1] as $key => $match_key)
		{
			$out[$match_key] = $match[2][$key];
		}
	}
	if (preg_match_all('/(sample_rate|channels|bits-per-sample): ([\d]*)/uis', $data, $match))
	{
		foreach ($match[1] as $key => $match_key)
		{
			$out[$match_key] = $match[2][$key];
		}
	}
	return ($out);
}


function exec_program($cmd, $dump = true)
{
	printvv($cmd . N);
	$out = '';
	$handle = popen($cmd, 'r');
	while (!feof($handle))
	{
		$out .= fgets($handle);
	}
	pclose($handle);
	if ($dump)
	{
		var_dump_vv($out);
	}
	return ($out);
}


function search_ext($ext, $dir = false, $recur = false)
{
	global $dany_folders;
	if (false === $dir)
	{
		global $pwd;
		$dir = $pwd;
	}
	$dir = realpath($dir);
	$ext_len = strlen('.' . $ext);
	$out = array ();
	if (!is_dir ($dir) && is_readable($dir))
	{
		$file = basename($dir);
		$dir = dirname($dir);
		$offset = strlen($file) - $ext_len;
		$full_name = $dir . '/' . $file;
		if ($offset >= 0 && (false !== strpos($file, '.' . $ext, $offset) ||
				     false !== strpos($file, '.' . strtoupper($ext), $offset)))
		{
			$out[] = $full_name;
		}
		return ($out);
	}
	if (!is_dir($dir))
	{
		return ($out);
	}
	$files = scandir($dir);
	foreach ($files as $file)
	{
		if (in_array($file, $dany_folders))
		{
			continue;
		}
		$offset = strlen($file) - $ext_len;
		$full_name = $dir . '/' . $file;
		if ($offset >= 0 && (false !== strpos($file, '.' . $ext, $offset) ||
				     false !== strpos($file, '.' . strtoupper($ext), $offset)))
		{
			$out[] = $full_name;
		}
		if ($recur && is_dir($full_name))
		{
			$out = array_merge($out, search_ext($ext, $full_name, true));
		}
	}
	return ($out);
}


function get_args()
{
	global $argv;
	$args = array ();
	$is_val = false;
	$have_path = false;

	// will add args with vals if need
	$args_with_vals = array ();
	foreach ($argv as $arg_key => $arg_val)
	{
		if (!$arg_key)
		{
			continue;
		}

		if ($is_val)
		{
			$args[$is_val] = $arg_val;
			$is_val = false;
			continue;
		}

		if (in_array ($arg_val, $args_with_vals))
		{
			$is_val = $arg_val;
		}
		else
		{
			if (!$have_path && 0 !== strpos($arg_val, '-'))
			{
				$args['path'] = $arg_val;
				$have_path = true;
			}
			else
			{
				$args[$arg_val] = false;
			}
		}
	}
	return ($args);
}

function check_args()
{
	global $allowed_args, $args;
	$not_used_args = $allowed_args;

	foreach ($args as $arg_key => $arg_val)
	{
		printvv('Проверяем аргумент - ' . $arg_key . N);
		foreach ($not_used_args as $nu_args_key => $nu_args_val)
		{
			printvv('Проверяем шаблон - ' . $nu_args_val . N);
			if (preg_match($nu_args_val, $arg_key))
			{
				printvv('Аргумент найден - ' . $arg_key . ' по шаблону ' . $nu_args_val . N . N);
				unset ($not_used_args[$nu_args_key]);
				continue (2);
			}
		}
	}

	if (isset ($args['-h']) || ((count($allowed_args) - count($not_used_args)) < count($args)) )
	{
		print ('Usage:' .	T . 'musiccoll [-r] [-nl] [-ro|-f] [path...]' . N);
		print (			T . 'musiccoll -tl|-sf|-st [-r] [-ro] [path...]' . N);
		print (			T . 'musiccoll -h' . N);
		print ('Путь по умолчанию: текущий каталог' . N);
		print ('-r' . 	T . 'Читать каталоги рекурсивно' . N);
		print ('-f' . 	T . 'Пересоздать целевой каталог, если будет найден' . N);
		print ('-ro' . 	T . 'Запрет на выполнение реальной работы' . N);
		print ('-nl' . 	T . 'Запрет на использование last.fm (артист, альбом, cover.jpg)' . N);
		print ('-sf' . 	T . 'Запрет на поиск и обработку flac файлов' . N);
		print ('-st' . 	T . 'Запрет на тегирование и переименование flac файлов' . N);
		print ('-tl' . 	T . 'Взять имена для flac треков из файла list.txt и записать в теги, только это' . N);
		print ('-h' . 	T . 'Показать это сообщение' . N);
		//print ('Если указана опция -, читает текущий ввод как список файлов и каталогов' . N);
		exit;
	}

	if (isset ($args['path']))
	{
		$args['path'] = realpath($args['path']);
	}

	printvv('Аргументы:' . N);
	var_dump_vv($args);
}


function rrmdir($dir)
{
	$files = glob(	$dir . '*',
			GLOB_MARK);
	foreach ($files as $file)
	{
		if (substr($file, -1) == '/')
		{
			rrmdir($file);
		}
		else
		{
			unlink($file);
		}
	}

	if (is_dir($dir))
	{
		rmdir($dir);
	}
}



function var_dump_v($obj)
{
	global $args;
	if (isset ($args['-v']) || isset ($args['-vv']))
	{
		return (var_dump ($obj));
	}
	return (-1);
}


function var_dump_vv($obj)
{
	global $args;
	if (isset ($args['-vv']))
	{
		return (var_dump ($obj));
	}
	return (-1);
}


function printv($msg)
{
	global $args;
	if (isset ($args['-v']) || isset ($args['-vv']))
	{
		return (print ($msg));
	}
	return (-1);
}


function printvv($msg)
{
	global $args;
	if (isset ($args['-vv']))
	{
		return (print ($msg));
	}
	return (-1);
}

?>
