// KGS Games - jQuery Plugin
// Lists games played on KGS 
// by Nate Eagle

(function($){

$.fn.kgsgames = function(o) {
    if (typeof o == 'string') {
        var instance = $(this).data('kgsgames'),
            args = Array.prototype.slice.call(arguments, 1);
        return instance[o].apply(instance, args);
    } else {
        return this.each(function() {
            // Swap 'plugin' with specific plugin name
            $(this).data('kgsgames', new $kgs(this, o));
        });
    }
}

var $kgs = $.kgsgames = function(elem, options) {
    this.options = $.extend({}, this.options, options || {});

    this.elem = elem;
    this.$elem = $(elem);

    this.build();
}

$kgs.prototype = {
    options: {
        // defaults
        archivesTag: '<ul />',
        fullArchiveLink: '<p />',
        fullArchiveLinkText: 'Full Archive',
        dateFormat: 'F jS', // format of date -- uses php strtodate rules
        hoursFresh: null, // by default, cached file is considered "fresh" (not in need of an update) for 24 hours
        noCache: null, // set to true to force rebuilding of the cache file... useful when changing settings
        numGames: 20, // maximum number of games to try to fetch from archives
        // a custom string that uses @VALUES to stand in for game result values
        /* valid @VALUES:

           @GAMESGF: a link to a game's SGF file
           @DATE: date of the game -- alter formatting via dateFormat option
           @BLACK: username & rank of person playing black
           @BLACKURL: link to black's archives on KGS
           @BLACKRESULT: either 'winner' or 'loser', depending on who won
           @WHITE: username & rank of person playing white
           @WHITEURL: link to white's archives on KGS
           @WHITERESULT: either 'winner' or 'loser', depending on who won
           @RESULT: game outcome, ie: 'B+7.5' or 'W+Res.'

           All @VALUES may be ommitted or repeated.
        */
        output: '<li><a href="http://eidogo.com/#url:@GAMESGF"><span class="date">@DATE:</span> <span class="black @BLACKRESULT">@BLACK</span> vs. <span class="white @WHITERESULT">@WHITE</span>: @RESULT</a>',
        ranked: true, // by default, only fetches ranked games
        tags: null, // set to t to return only tagged games 
        url: null,
        username: null,
        widgetName: null // if more than one KGS widget is on a page, specify a widget name so that it will use a different file to cache records
    },
    build: function() {
        this.getGames();
    },
    getGames: function() {
        var that = this;

        ajaxOpts = {
            username: that.options.username,
            dateFormat: that.options.dateFormat,
            hoursFresh: that.options.hoursFresh,
            numGames: that.options.numGames,
            noCache: that.options.noCache,
            tags: that.options.tags,
            widgetName: that.options.widgetName
        }
        // Don't send any params with null values
        for (val in ajaxOpts) { 
            if (ajaxOpts[val] === null) { delete ajaxOpts[val]; }
        } 

        $.ajax({
            url: this.options.url,
            data: ajaxOpts,
            dataType: 'json',
            success: $.proxy(function(data) { that.displayGames(data); }, this)
        });
    },
    displayGames: function(games) {
        // Build the game list
        var archives = $(this.options.archivesTag).appendTo(this.$elem);

        for(var i=games.length-1;i>=0;i--) {
            var output = this.options.output,
                white, black;

            // Set winner & loser
            if (games[i].result.substring(0, 1) == 'W') {
                whiteresult = 'winner';
                blackresult = 'loser';
            } else {
                whiteresult = 'loser';
                blackresult = 'winner';
            }

            output = output.replace(/@GAMESGF/g, games[i].sgf);
            output = output.replace(/@DATE/g, games[i].date); 
            output = output.replace(/@BLACKRESULT/g, blackresult); 
            output = output.replace(/@BLACKURL/g, games[i].blackurl); 
            output = output.replace(/@BLACK/g, games[i].black); 
            output = output.replace(/@WHITERESULT/g, whiteresult); 
            output = output.replace(/@WHITEURL/g, games[i].whiteurl); 
            output = output.replace(/@WHITE/g, games[i].white); 
            output = output.replace(/@RESULT/g, games[i].result); 

            archives.prepend(output);
        }

        // Add archive link
        $(this.options.fullArchiveLink, {
            'class': 'archive',
            html: '<a href="http://www.gokgs.com/gameArchives.jsp?user=' + this.options.username + '">' + this.options.fullArchiveLinkText + '</a>'
        }).appendTo(this.$elem);
    }
}

})(jQuery);
