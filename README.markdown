First, this is the problem: [LiveJournal Comments](http://www.brockboland.com/2006/11/livejournal-comments).  That post explains things, so you should read it.

The script will screen scrape all comments made on all current posts in the syndicated account.  For each comment, it will check the comments table to see if it already exists, and if not, it will create it.  These comments will be unpublished by default; you'll need to go into the admin panel and approve any that you want to keep.

There are two versions of the script.  The first was written in December 2006 for use with Movable Type.  In early 2008, I switched to WordPress, and updated it to work with that.  Around the same time, my web host changed some security settings, so the WordPress version uses curl to get the content from LiveJournal.  The Movable Type and WordPress versions are both available above.  They both have the same functionality, but work a little differently to achieve it, and produce slightly different output to the browser.

This script is an ugly ugly hack, but it does the job and that's all I wanted.  I'm only posting it here for the sake of those who want to do the same thing - I make NO guarantees about this script, what it will do, or what might go wrong.  Use this at your own risk.  Also, not that it doesn't handle HTML very well.  Take, for example, a link tag.  The parser sees the text before the link and the text of the link as two distinct text nodes, and ignores the `a` node.  As such, something like "Click <a href="http://www.google.com">here</a> to visit Google" will come through as:

  Click

  here

  to visit Google
  
Three text nodes, so three lines of text.
Obviously, this isn't ideal.  But, as I've said a dozen times before, this is a dirty hack to fill a particular need, so it's good enough for me.  If you want to improve it, feel free.
	
That having been said, the script can be downloaded above, along with the HtmlParser from [Jose Solorzano](http://jexpert.us).  There are some variables in ljcommentgrabber.php that should be set.  Put both scripts in the same directory (remove the .txt extension, of course), and just point a browser at ljcommentgrabber.php (obviously, you should rename this for brevity).

If you just want to use the comment-scraping logic to inject comments into some other blog system, you only need to change the addComment() function.

That's pretty much it.  I wouldn't run it too often: I don't know if LJ will block your IP for making too many requests.

This code is licensed under a [Creative Commons Attribution-ShareAlike 2.5 License](http://creativecommons.org/licenses/by-sa/2.5/).