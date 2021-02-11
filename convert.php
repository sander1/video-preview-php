<?php
define("FFMPEG_PATH", "ffmpeg");
define("FFPROBE_PATH", "ffprobe");

define("CHOP_SECONDS", 30);
define("NUMBER_OF_PARTS", 8);
define("PART_DURATION", 1.25);
define("FPS", 25);

define("REGEX_VIDEO_SIZE", "`\d+x\d+`");

// input file %s : (string)
define("FFPROBE_IFRAMES", FFPROBE_PATH . " -v error -skip_frame nokey -show_entries frame=pkt_pts_time -select_streams v -of csv=p=0 %s");

// -ss %F : start seconds (float)
// -i %s : file (string)
// -t %F : suration in seconds (float)
define("FFMPEG_CROPDETECT", FFMPEG_PATH . " -ss %F -i %s -t %F -vf \"cropdetect=24:16:0\" -f null - 2>&1 | awk '/crop/ { print \$NF }' | tail -1");

// -ss %F : start seconds (float)
// -i %s : file (string)
// -t %F : suration in seconds (float)
// -an %s_%04d.mp4 : output file name with output part number (integer, zerofilled, e.g. 0001)
define("FFMPEG_PART", FFMPEG_PATH . " -hide_banner -loglevel warning -y -ss %F -i %s -t %F -vf \"%s, scale=(iw*sar)*max(%d.1/(iw*sar)\,%d.1/ih):ih*max(%d.1/(iw*sar)\,%d.1/ih), crop=%d:%d, fps=fps=" . FPS . "\" -an %s_%04d.mp4");

define("CONCAT_FILE_LINE", "file ./%s_%04d.mp4" . PHP_EOL);
define("CONCAT_FILE", "%s_parts.txt");

// -i %s_parts.txt : concat text file (string)
// output file %s-preview.mp4 : (string)
define("FFMPEG_CONCAT", FFMPEG_PATH . " -hide_banner -loglevel warning -y -f concat -safe 0 -i %s_parts.txt -c copy -bsf:v \"filter_units=remove_types=6\" -movflags +faststart \"%s-preview.mp4\"");

// Is filename given?
if (!isset($argv[1])) {
	echo "No input file\n";
	exit(0);
}

// Does file exist?
if (!file_exists($argv[1])) {
	echo "File doesn't exist\n";
	exit(0);
}

// Is a valid output size given?
if (isset($argv[2])) {
	if (1 !== preg_match(REGEX_VIDEO_SIZE, $argv[2])) {
		echo "Invalid output size\n";
		exit(0);
	}
} else {
	$argv[2] = "400x225";
}

list($width, $height) = explode("x", $argv[2]);

$input_file_name = escapeshellarg($argv[1]);
$tmp_file_name = sha1($argv[1]);

$path_parts = pathinfo($argv[1]);
$base_name = $path_parts['filename'];

echo "*** FFprobe command: " . sprintf(FFPROBE_IFRAMES, $input_file_name) . "\n";
$iframes_data = shell_exec(sprintf(FFPROBE_IFRAMES, $input_file_name));
$iframes_list = explode(PHP_EOL, trim($iframes_data));

$highest_allowed = end($iframes_list) - CHOP_SECONDS;

for ($i=0, $j=sizeof($iframes_list); $i < $j; $i++) {

	if ($iframes_list[$i] < CHOP_SECONDS || $iframes_list[$i] > $highest_allowed) {
		unset($iframes_list[$i]);
	}
}

$iframes_list = array_values($iframes_list);

$mod = round(sizeof($iframes_list) / NUMBER_OF_PARTS);
$part = 0;
$concat_file_contents = "";

for ($i=0, $j=sizeof($iframes_list); $i < $j; $i++) {

	if (0 === $i % $mod) {

		// Determine crop values
		echo "*** FFmpeg command: " . sprintf(FFMPEG_CROPDETECT, $iframes_list[$i], $input_file_name, PART_DURATION) . "\n";
		$crop_values = trim(exec(sprintf(FFMPEG_CROPDETECT, $iframes_list[$i], $input_file_name, PART_DURATION)));
		echo "*** Crop values: " . $crop_values . "\n";

		echo "*** FFmpeg command: " . sprintf(FFMPEG_PART, $iframes_list[$i], $input_file_name, PART_DURATION, $crop_values, $width, $height, $width, $height, $width, $height, $tmp_file_name, $part) . "\n";
		exec(sprintf(FFMPEG_PART, $iframes_list[$i], $input_file_name, PART_DURATION, $crop_values, $width, $height, $width, $height, $width, $height, $tmp_file_name, $part));

		if (filesize(sprintf("./%s_%04d.mp4", $tmp_file_name, $part)) > 0) {
			$concat_file_contents .= sprintf(CONCAT_FILE_LINE, $tmp_file_name, $part);
		}

		$part++;
	}

	if (NUMBER_OF_PARTS === $part) {
		break;
	}
}

file_put_contents(sprintf(CONCAT_FILE, $tmp_file_name), $concat_file_contents);

exec(sprintf(FFMPEG_CONCAT, $tmp_file_name, $base_name));

array_map("unlink", glob($tmp_file_name . "_[0-9][0-9][0-9][0-9].mp4"));
unlink(sprintf(CONCAT_FILE, $tmp_file_name));
?>
