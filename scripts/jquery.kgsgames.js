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
        archiveLinkText: 'Full Archive',
        dateFormat: 'F jS', // format of date -- uses php strtodate rules
        noCache: null, // set to true to force rebuilding of the cache file... useful when changing settings
        numGames: 20, // maximum number of games to try to fetch from archives
        ranked: true, // by default, only fetches ranked games
        // Customizable structure - can be replaced, for instance, with <table />, <tr />, and <td />
        structure: {
            archives: '<ul />',
            row: '<li />',
            item: '<span />',
            archiveLink: '<p />',
            space: ' '
        },
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
        var archives,
            tagArchives = this.options.structure.archives,
            tagRow = this.options.structure.row,
            tagItem = this.options.structure.item,
            tagArchiveLink = this.options.structure.archiveLink,
            space = this.options.structure.space;

        archives = $(tagArchives).appendTo(this.$elem);
        for(var i=games.length-1;i>=0;i--) {
            var white, black;

            var row = $(tagRow).prependTo(archives);

            // Set winner & loser
            if (games[i].result.substring(0, 1) == 'W') {
                white = 'winner';
                black = 'loser';
            } else {
                white = 'loser';
                black = 'winner';
            }

            var date = $(tagItem, {
                html: '<a href="http://eidogo.com/#url:' + games[i].sgf + '">' + games[i].date + '</a>' + space,
                'class': 'date'
            }).appendTo(row);
            
            var bl = $(tagItem, {
                text: games[i].black + space,
                'class': 'black ' + black
            }).appendTo(row);

            var vs = $(tagItem, {
                text: 'vs.' + space,
                'class': 'vs'
            }).appendTo(row);

            var wh = $(tagItem, {
                text: games[i].white + space,
                'class': 'white ' + white 
            }).appendTo(row);

            var result = $(tagItem, {
                text: games[i].result,
                'class': 'result'
            }).appendTo(row);

        }

        // Add archive link
        $(tagArchiveLink, {
            'class': 'archive',
            html: '<a href="http://www.gokgs.com/gameArchives.jsp?user=' + this.options.username + '">' + this.options.archiveLinkText + '</a>'
        }).appendTo(this.$elem);
    }
}

})(jQuery);
