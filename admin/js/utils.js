(function($, window) {
    if (typeof window.ssmptmsUtils === 'undefined') {
        window.ssmptmsUtils = {};
    }

    window.ssmptmsUtils.lockActionLink = function($link, loadingText) {
        if (!$link || !$link.length) {
            return false;
        }
        if ($link.data('loading')) {
            return false;
        }
        $link.data('loading', true);
        $link.data('original-text', $link.text());
        if (loadingText) {
            $link.text(loadingText);
        }
        $link.addClass('ssmptms-action-disabled').attr('aria-busy', 'true');
        return true;
    };

    window.ssmptmsUtils.unlockActionLink = function($link) {
        if (!$link || !$link.length) {
            return;
        }
        var original = $link.data('original-text');
        if (original) {
            $link.text(original);
        }
        $link.removeClass('ssmptms-action-disabled')
            .removeAttr('aria-busy')
            .data('loading', false);
    };

    window.ssmptmsUtils.setSubmitLoading = function($button, $spinner, loading, loadingText) {
        if (!$button || !$button.length) {
            return;
        }

        var isInput = $button.is('input');
        var getTextElement = function() {
            var $textEl = $button.find('.ssmptms-button-text').first();
            if ($textEl.length) {
                return $textEl;
            }

            var collectedText = '';
            $button.contents().each(function() {
                if (this.nodeType === 3) {
                    collectedText += this.nodeValue;
                    $(this).remove();
                }
            });

            $textEl = $('<span class="ssmptms-button-text"></span>').text(collectedText);
            var $firstSpinner = $button.find('.spinner').first();
            if ($firstSpinner.length) {
                $firstSpinner.before($textEl);
            } else {
                $button.prepend($textEl);
            }

            return $textEl;
        };
        var getText = function() {
            if (isInput) {
                return $button.val();
            }
            return getTextElement().text();
        };
        var setText = function(text) {
            if (isInput) {
                $button.val(text);
            } else {
                getTextElement().text(text);
            }
        };

        if (loading) {
            if (!$button.data('original-text')) {
                $button.data('original-text', getText());
            }
            if (loadingText) {
                setText(loadingText);
            }
            $button.prop('disabled', true).addClass('ssmptms-action-disabled');
            if ($spinner && $spinner.length) {
                $spinner.addClass('is-active');
            }
        } else {
            var original = $button.data('original-text');
            if (original) {
                setText(original);
            }
            $button.prop('disabled', false).removeClass('ssmptms-action-disabled');
            if ($spinner && $spinner.length) {
                $spinner.removeClass('is-active');
            }
        }
    };
})(jQuery, window);
