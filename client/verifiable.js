/**
 * Simple jQuery-backed JS logic for verifiable's CMS UI.
 * 
 * @package silverstripe-verifiable
 * @author Russell Michell 2018 <russ@theruss.com>
 */

(function($) {
    $(document).ready(function($) {
        let $root = $('#Root_Verify');
        
        $root.entwine({
            
            onmatch: function() {
                var urlArray = $('#tab-Root_Verify').attr('href').split('#')[0].split('/');
                
                let type = $('input[name="Type"]').val();                
                let id = urlArray[urlArray.length -1];
                let version = $('select[name="Version"]').val();
                let controllerUrl = '/verifiable/verifyhash/' + type + '/' + id + '/' + version;
                
                $('input[name="action_doVerify"]').on('click', function() {                    
                    $('.cms-content-fields').append(spinkitSpinner());
                    
                    $.get(controllerUrl)
                        .done(function(data) {
                            var message = data.Status,
                                cssClass = message !== 'Verified' ? 'bad' : 'good';
                                
                            $root.append($('<p class="message ' + cssClass + '">' + message + '</p>'));
                            $('.sk-circle').remove();
                        })
                        .fail(function(data) {
                            $root.append($('<p class="message bad">Request Failed</p>'));
                            $('.sk-circle').remove();
                        });
                });
            }
        });
        
        // CSS Spinner courtesy of: http://tobiasahlin.com/spinkit/
        function spinkitSpinner() {
            let spinnerMarkup = '<div class="sk-circle">';
            
            for (var i=1; i<=12; i++) {
                spinnerMarkup += '<div class="sk-circle' + i + ' sk-child"></div>';
            }
            
            spinnerMarkup += '</div>';
            
            return $(spinnerMarkup);
        }
        
    });
})(jQuery);