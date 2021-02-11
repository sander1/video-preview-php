# What is this?
This is a script written in PHP to create small video previews like you can see on YouTube. It makes use of [ffmpeg](https://ffmpeg.org/) and [ffprobe](https://ffmpeg.org/ffprobe.html) to do so.

## Why PHP, why not bash or something else?
Because I'm a web guy and comfortable with PHP.

# How do I use it?
In its simplest form you call the script and give it a file and (optionally) video dimensions for the output:
```
php convert.php myVideo.mp4 400x300
```
The result will be a small preview video: `myVideo-preview.mp4`

Use quotes or escape spaces if your filename contains spaces:
```
php convert.php "my video.mp4" 400x300
php convert.php my\ video.mp4 400x300
```
## Some configuration
Some tuning can be done with the settings at the top of the script:

* `FFMPEG_PATH`: Path to the ffmpeg binary. If globally installed leave it at `ffmpeg`
* `FFPROBE_PATH`: Path to the ffprobe binary. If globally installed leave it at `ffprobe`
* `CHOP_SECONDS`: The number of seconds to skip at the beginning and end of a file, to skip titles, default `30`
* `NUMBER_OF_PARTS`: The number of segments you want your preview to have, default `8`
* `PART_DURATION`: The length of each segment in seconds, default `1.25`
* `FPS`: Frames per second for the output file, default `25`

The reason why those values are not part of the command line input is because this script will most likely run as an automated task or cronjob and all the video previews need to be the same.

