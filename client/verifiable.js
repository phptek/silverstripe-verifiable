/**
 * Simple entwine-backed JS logic for verifiable's CMS UI.
 * 
 * @package silverstripe-verifiable
 * @author Russell Michell 2018 <russ@theruss.com>
 */

(function($) {
    $(document).ready(function($) {
        let version = $('select[name="Version"] option:selected').val();
        
        $('select[name="Version"]').entwine({
            onchange: function() {
                version = $(this).val();
            }
        });
        
        $('.cms-edit-form').entwine({
            
            onmatch: function() {
                let $root = $('#Root_Verify');
                let urlArray = $('#tab-Root_Verify').attr('href').split('#')[0].split('/');
                let type = $('input[name="Type"]').val();                
                let id = urlArray[urlArray.length -1];
                
                $('button[name="action_doVerify"]').on('click', function() {                    
                    let $button = $(this);
                    let controllerUrl = 'verifiable/verifyhash/' + type + '/' + id + '/' + version;
                    
                    $button
                        .addClass('btn--loading loading')
                        .append(spinner());
                
                    $('.v-status').remove();
                    
                    $.get(controllerUrl)
                        .done(function(data) {
                            var message = '<strong>' + data.StatusNice + '</strong> ' + data.StatusDefn,
                                cssClass = data.StatusCode === 'STATUS_VERIFIED' || data.StatusCode === 'STATUS_PENDING' ? 'good' : 'bad';
                                
                            $root.prepend($('<p class="message v-status ' + cssClass + '">' + message + '</p>'));
                            $button
                                .addClass('font-icon-tick')
                                .removeClass('btn--loading loading');
                        
                            $('.btn__loading-icon').remove();
                        })
                        .fail(function() {
                            var msg = '<p class="message v-status bad">';
                            msg += '<strong>Request Failed</strong> A connection problem';
                            msg += ' meant that this request could not be processed.';
                            msg += ' Please try again in a moment';
                            msg += '</p>';
                            $root.prepend($(msg));
                            $button
                                .addClass('font-icon-cross')
                                .removeClass('btn--loading loading');
                            
                            $('.btn__loading-icon').remove();
                        });
                });
            }
        });
        
        /**
         * Generate the CMS' "dot dot dot" spinner markup.
         * There's probably some function somewehre to do this for us.
         * 
         * @return DomNode
         */
        function spinner() {
            let spinnerMarkup = '<div class="btn__loading-icon">';
            
            for (var i=1; i<=3; i++) {
                spinnerMarkup += '<span class="btn__circle btn__circle--' + i + '"></span';
            }
            
            spinnerMarkup += '</div>';
            
            return $(spinnerMarkup);
        }
        
    });
})(jQuery);