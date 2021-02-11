<?php
define("FFMPEG_PATH", "ffmpeg");
define("FFPROBE_PATH", "ffprobe");

define("CHOP_SECONDS", 30);
define("NUMBER_OF_PARTS", 8);
define("PART_DURATION", 1.25);
define("FPS", 25);

////////// No need to change things below this line //////////

define("REGEX_VIDEO_SIZE", "`\d+x\d+`");

// %s : ffprobe path (string)
// input file %s : (string)
define("FFPROBE_IFRAMES", "%s -v error -skip_frame nokey -show_entries frame=pkt_pts_time -select_streams v -of csv=p=0 %s");

// %s : ffmpeg path (string)
// -ss %F : start seconds (float)
// -i %s : file (string)
// -t %F : length in seconds (float)
define("FFMPEG_CROPDETECT", "%s -ss %F -i %s -t %F -vf \"cropdetect=24:16:0\" -f null - 2>&1 | awk '/crop/ { print \$NF }' | tail -1");

// %s : ffmpeg path (string)
// -ss %F : start seconds (float)
// -i %s : file (string)
// -t %F : length in seconds (float)
// fps=fps=%F : frames per second (float)
// -an %s_%04d.mp4 : output file name with output part number (integer, zerofilled, e.g. 0001)
define("FFMPEG_PART", "%s -hide_banner -loglevel warning -y -ss %F -i %s -t %F -vf \"%s, scale=(iw*sar)*max(%d.1/(iw*sar)\,%d.1/ih):ih*max(%d.1/(iw*sar)\,%d.1/ih), crop=%d:%d, fps=fps=%F\" -an %s_%04d.mp4");

define("CONCAT_FILE_LINE", "file ./%s_%04d.mp4" . PHP_EOL);
define("CONCAT_FILE", "%s_parts.txt");

// %s : ffmpeg path (string)
// -i %s_parts.txt : concat text file (string)
// output file %s-preview.mp4 : (string)
define("FFMPEG_CONCAT", "%s -hide_banner -loglevel warning -y -f concat -safe 0 -i %s_parts.txt -c copy -bsf:v \"filter_units=remove_types=6\" -movflags +faststart \"%s-preview.mp4\"");

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

$cli = sprintf(FFPROBE_IFRAMES, FFPROBE_PATH, $input_file_name);
echo sprintf("*** FFprobe command: %s\n", $cli);
$iframes_data = shell_exec($cli);
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
		$cli = sprintf(FFMPEG_CROPDETECT, FFMPEG_PATH, $iframes_list[$i], $input_file_name, PART_DURATION);
		echo sprintf("*** FFmpeg command: %s\n", $cli);
		$crop_values = trim(exec($cli));
		echo "*** Crop values: " . $crop_values . "\n";

		$cli = sprintf(FFMPEG_PART, FFMPEG_PATH, $iframes_list[$i], $input_file_name, PART_DURATION, $crop_values, $width, $height, $width, $height, $width, $height, FPS, $tmp_file_name, $part);
		echo sprintf("*** FFmpeg command: %s\n", $cli);
		exec($cli);

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

exec(sprintf(FFMPEG_CONCAT, FFMPEG_PATH, $tmp_file_name, $base_name));

array_map("unlink", glob($tmp_file_name . "_[0-9][0-9][0-9][0-9].mp4"));
unlink(sprintf(CONCAT_FILE, $tmp_file_name));
?>
