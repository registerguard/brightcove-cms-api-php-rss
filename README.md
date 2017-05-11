Brightcove is deprecating their old media API in favor of their new CMS API. The Register-Guard used the media API to return an RSS feed to populate videos into our apps. At present, Brightcove does not have any examples beyond JavaScript so I took their JS and converted it into a PHP script. This repo contains a full example of that new script, the proxy script for the CMS API and minor documentation. All secrets have been removed but this script should work for any Brightcove client with the proper API credentials.

## The problem

We populated our news app with all Brightcove videos with [this old media API URL](http://api.brightcove.com/services/library?command=search_videos&none=tag%3Asports&page_size=25&video_fields=id%2Cname%2CshortDescription%2ClongDescription%2CpublishedDate%2CFLVURL%2CFLVFullLength&media_delivery=http&sort_by=PUBLISH_DATE%3ADESC&page_number=0&get_item_count=true&output=mrss&token=LWnGOsLL8Z0t-pK6WI9FFPm32-FxWTJdGPkGmFVz2LAnRwELJsR9hg) and we populated our sports app with only videos tagged with "oregon" AND "football" AND "sports" using [this old media API URL](http://api.brightcove.com/services/library?command=search_videos&all=tag%3Aoregon&all=tag%3Afootball&all=tag%3Asports&page_size=25&video_fields=id%2Cname%2CshortDescription%2ClongDescription%2CpublishedDate%2CFLVURL%2CFLVFullLength&media_delivery=http&sort_by=PUBLISH_DATE%3ADESC&page_number=0&get_item_count=true&output=mrss&token=LWnGOsLL8Z0t-pK6WI9FFPm32-FxWTJdGPkGmFVz2LAnRwELJsR9hg).

I needed a new URL to pass to our app vendor that would render an RSS feed to filled with those two sets of videos (or any other tag combination).

Brightcove does not offer that any more as the new CMS API uses OAuth. They do have a [JavaScript example](https://docs.brightcove.com/en/video-cloud/cms-api/samples/mrss-generator.html) to work off of though.

## RG set up

Given our current server situation and the requirements of this project (just get it done ASAP), I decided that doing this in PHP would be the most efficient way to accomplish the goals.

The example proxy file that Brightcove provides is PHP, so that also helped sway my decision. We used to have servers that could handle this (blogs) but those have been shut down and the best place I could find was `http://advertising.registerguard.com/internal/brightcove/` (log in as newsoper).

This certainly isn't perfect, but Brightcove is more or less an advertising product so we'll call it good.

### API creds

If the credentials are ever lost, you'll need to go generate new ones. The current key we use is called RG app feed, but the secret will be lost. These credentials only need Playlist Read and Video Read, despite what the BC docs say. You should not add any additional rights because this PHP script is highly insecure and we don't want write privileges out there in the open. I don't like having the read privileges out there but this just needs to get done.

## Files

### proxy.php

This file comes from an example given by BC on the [MRSS generator page](https://docs.brightcove.com/en/video-cloud/cms-api/samples/mrss-generator.html#proxy). We went with the simple one where you hard-code the credentials because we only need one for this.

I modified this file to be able to handle URL encoding and added the proper credentials. To be totally honest, I don't completely remember why I added `$myPostArgs = filter_input_array(INPUT_POST);` but it works so we'll call it good... I think it had to do with being able to handle the encoding... Maybe...

### index.php

Ok, this is the show time file. I started with the JavaScript from the example and tried to convert it to PHP. That proved to be more work than was necessary. So I did it the other way around, and started with the simplest possible thing (getting list of videos), then got more sophisticated (get video source URLs). You can find some of those experiments in the scratch folder in the brightcove directory on advertising/internal. After I got all the goods, I formatted it as RSS.

**THEN**, I tried to get tag-based queries working. Which, I probably should have done earlier, but whatevs. I setup the test.php in scratch to work on that as a simple example, because nothing I tried was working. I went back and forth with Brightcove support for a few weeks. Finally they noticed my dumb mistake was missing a plus sign before tags. I'll copy that email exchange into the wiki. Brendan Johl was very helpful though, he stuck with it until we got it figured out and kicked it up to engineering teams when needed.

I've tried to comment out this file heavily, here is the basic flow:

1. Go to proxy, get last x videos (this is determined by URL param, defaults to 10, could go to 25)
1. Loop over those videos and go get the source url
1. Put all that data into a handy array
1. Do the RSS and loop over the handy array

Some nice things to know:

* You can set the count as a URL param (`count=13`)
* You can set tag queries as URL param (`tags="oregon","sports","football"`)
  * Note on this, I've set it up so that it gets only videos that have ALL of the tags, this could be altered by editing the logic on ~ line 47 which sets the string for the tags query
* All of the times are in GMT, because that was just easier than converting to timezone
* The `clean()` function is pretty simple and should probably be more sophisticated and include more than ampersands and equal signs (although, that is all that is needed at present)
* The loop to get the video source is also rudimentary. It just looks for MP4s from that server, which I deemed to be the best quality video
* Also in that loop, we the setting is should be something like this:
  * `$sizes[$source['width']] = $source['src'];`
  * `$sizes['960'] = http://...`
  *  Hopefully that makes sense...




