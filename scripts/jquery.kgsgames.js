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
        itemTag: '<li />',
        month: null,
        noCache: false, // set to true to force rebuilding of the cache file... useful when changing settings
        numGames: 20,
        tag: null, // use to add an optional filter for games with a certain tag
        url: null,
        username: null,
        year: null
    },
    build: function() {
        this.getGames();
    },
    getGames: function() {
        var that = this;
        $.ajax({
            url: this.options.url,
            data: {
                username: that.options.username,
                numGames: that.options.numGames,
                noCache: that.options.noCache
            },
            dataType: 'json',
            success: $.proxy(function(data) { that.displayGames(data);
            }, this)
        });
    },
    displayGames: function(games) {
        // Hide game container initially
        this.$elem.hide();

        // Build the game list
        for(var i=games.length-1;i>=0;i--) {
            var white, black;
            // Set winner & loser
            if (games[i].result.substring(0, 1) == 'W') {
                white = 'winner';
                black = 'loser';
            } else {
                white = 'loser';
                black = 'winner';
            }
            $(this.options.itemTag, {
                html: '<span class="date">' + games[i].date + '</span> <a href="http://eidogo.com/#url:' + games[i].sgf +'"> <span class="' + white + '">' + games[i].white + '</span> vs. <span class="' + black + '">' + games[i].black + '</span>'
            }).prependTo(this.$elem);
        }

        // Add archive link
        $('<p />', {
            'class': 'archive',
            html: '<a href="http://www.gokgs.com/gameArchives.jsp?user=' + this.options.username + '">Full Archive</a>'
        }).insertAfter(this.$elem);

        // Animate open game container
        this.$elem.fadeIn('slow');
    }
}

})(jQuery);
