/**
 * Simple entwine-backed JS logic for verifiable's GUI.
 * 
 * @package silverstripe-verifiable
 * @author Russell Michell 2018 <russ@theruss.com>
 * @todo Refactor into two components:
 * 1). sitetree-verifiable-component.js
 * 2). file-verifiable-component.js (React) - this should really render as an option
 * on the footer's "Bulk Actions" toolbar, as an action hanging off the "..." link
 */

(function($) {
    $.entwine('ss.tree', function($) {
        
        $('.cms-edit-form, #Form_fileEditForm').entwine({
            
            onmatch: function() {
                $('.v-status, .verifiable-extra-data').remove();
                
                let version = $('select[name="Version"] option:selected').val();                
                let urlArray = location.href.split('#')[0].split('/');
                let type = $('input[name="Type"]').val().replace(/\\/g, '-');
                let id = urlArray[urlArray.length -1];
                
                $('select[name="Version"]').entwine({
                    onchange: function() {
                        version = $(this).val();
                    }
                });
                
                $('button[name="action_doVerify"]').on('click', function() {  
                    $('.v-status, .verifiable-extra-data').remove();

                    let $button = $(this);
                    let controllerUrl = 'verifiable/verifyhash/' + type + '/' + id + '/' + version;
                    let cleanedUrl = controllerUrl.replace(/(\?[\w]+=[\w]+)/, ''); // asset-admin adds query-strings
                    
                    $button
                        .addClass('btn--loading loading')
                        .append(spinner());
                    
                    $.get(cleanedUrl)
                        .done(function(data) {
                            $('.message.intro').remove();
                            
                            let message = '';
                            let isInitialOrPending = (
                                data.Status.Code === 'STATUS_INITIAL' ||
                                data.Status.Code === 'STATUS_PENDING'
                            );
                    
                            message += `<strong>${data.Status.Nice}</strong> ${data.Status.Def}`;
                    
                            if (data.Status.Code === 'STATUS_VERIFIED_OK') {
                                msgCssClass = 'good';
                                btnCssClass = 'font-icon-tick';
                                message += '<span class="font-icon-cog"></span><a href="#" id="tools">Verification Tools</a></span>.';
                                message += extraGeneral({
                                    proof: data.Proof,
                                    record: data.Record,
                                    status: data.Status
                                }) + extraChainpoint({
                                    proof: data.Proof,
                                    record: data.Record,
                                    status: data.Status
                                });
                            } else {
                                msgCssClass = isInitialOrPending ? 'good' : 'bad';
                                btnCssClass = isInitialOrPending ? 'font-icon-globe-1' : 'font-icon-cross-mark';
                            }
                                
                            $('<p class="message v-status ' + msgCssClass + '">' + message + '</p>').prependTo(attachMessageTo());
                            $button
                                .removeClass('font-icon-cross-mark font-icon-tick')
                                .addClass(btnCssClass)
                                .removeClass('btn--loading loading');
                        
                            $('.btn__loading-icon').remove();
                        })
                        .fail(function() {
                            let msg = '<p class="message v-status bad">';
                            msg += '<strong>Request Failed</strong> A connection problem';
                            msg += ' meant that this request could not be processed.';
                            msg += ' Please try again in a moment';
                            msg += '</p>';
                            $(msg).prependTo(attachMessageTo());
                            $button
                                .removeClass('font-icon-cross-mark font-icon-tick')
                                .addClass('font-icon-cross-mark')
                                .removeClass('btn--loading loading');
                            
                            $('.btn__loading-icon').remove();
                        });
                });
            }
        });
        
        // Show or hide the 'advanced" UI comprising logic from chainpoint-parse parse()
        // to validate our JSON proof's hashes
        $('#tools').entwine({
            onclick: function(e) {
                e.preventDefault();
                $('.message.bad.manual').remove();
                $('.verifiable-extra-data').toggleClass('hide');
            }
        });
        
        /**
         * General overview.
         * 
         * Keys into "<div.section-1-text"> setup in updateCMSFields().
         * 
         * @param  {Object}  data Merkle Root, local + remote hash, block height etc
         * @return {String}
         * @todo   Inject: <span class="help">what\'s this?</span> into each <th>
         */
        function extraGeneral(data) {
            let extra = '';
            
            extra += '<div class="verifiable-extra-data hide">';
                extra += '<p class="message notice"><strong>Overview:</strong><br/>'
                    + ' Each time a new version is created, its data is compacted into'
                    + ' a fixed-length digital hash. This makes it relatively simple'
                    + ' to tell whether the "local" and "remote" data representations'
                    + ' differ or not. You actually only need to compare the'
                    + ' first few characters in order to tell whether they differ.'
                    + ' The "Merkle Root" is the data that is actually stored on the'
                    + ' 3rd party immutible storage, and is partially derived'
                    + ' from this version\'s hash.</p>';
                extra += '<table class="verifiable-fields-table">';
                    extra += '<tr>';
                        extra += `<th>Version no.</th><td>${data.record.Version}</td>`;
                    extra += '</tr><tr>';
                        extra += `<th>Version Created</th><td>${data.record.CreatedDate}</td>`;
                    extra += '</tr><tr>';
                        extra += `<th>Version hash</th><td>${data.proof.Hashes.local}</td>`;
                    extra += '</tr><tr>';
                        extra += `<th>Chainpoint hash</th><td>${data.proof.Hashes.remote}</td>`;
                    extra += '</tr><tr>';
                        extra += `<th>Chainpoint UUID</th><td>${data.proof.UUID}</td>`;
                    extra += '</tr><tr>';
                        extra += `<th>BTC Merkle root</th><td>${data.proof.MerkleRoot}</td>`;
                    extra += '</tr><tr>';
                        extra += `<th>BTC Block height</th><td>${data.proof.BlockHeight}</td>`;
                    extra += '</tr><tr>';
                        extra += `<th>BTC Submission date</th><td>${data.proof.SubmittedDate}</td>`; // BTC date
                    extra += '</tr><tr>';
                        extra += `<th>BTC TXID</th><td>${data.proof.TXID}</td>`;
                    extra += '</tr><tr>';
                        extra += `<th>BTC OP_RETURN</th><td>${data.proof.OPRET}</td>`;
                    extra += '</tr>';
                extra += '</table>';
            extra += '</div>';
            
            return extra;
        }
        
        /**
         * Chainpoint overview.
         *  
         * Show the full V3 Chainpoint Proof and a feature that allows users to
         * validate the proof's hashes, including the Merkle Hash, by linking the
         * latter to a block explorer for example.
         * 
         * Keys into "<div.section-1-text"> setup in updateCMSFields().
         *
         * @param  {Object} data Merkle Root, local + remote hash, block height etc.
         * @return {String}
         */
        function extraChainpoint(data) {
            let extra = '';
            
            extra += '<div class="verifiable-extra-data hide">';
                extra += '<p class="message notice"><strong>Chainpoint:</strong><br/>'
                    + ' A <a href="https://chainpoint.org/" target="_blank">ChainPoint</a> proof is:'
                    + ' "<strong>an open standard for creating a timestamp proof'
                    + ' of any data, file, or processs</strong>". Whenever a new'
                    + ' version is created, and we send its hash representation to the'
                    + ' 3rd party immutible storage, we receive a chainpoint "receipt" back'
                    + ' which contains all the meta-data that was used to construct'
                    + ' the "Root Hash" that is ultimately stored in the immutible storage itself.</p>';
                extra += `<div id="receipt" class="receipt">${data.proof.ChainpointProof}</div>`;

                if (data.proof.ChainpointViz) {
                    extra += '<p class="message notice"><strong>Visualisation:</strong><br/>'
                        + ' A really good visualisation would comprise a full or partial <a href="https://en.wikipedia.org/wiki/Merkle_tree" target="_blank">Merkle Tree</a>.'
                        + ' But because your content hash submitted to the Bitcoin blockchain is only one of thousands,'
                        + ' showing a full tree would be impossible. Regardless, <a href="https://chainpoint.org/" target="_blank">ChainPoint</a>'
                        + ' itself doesn\'t give us all the data to reconstruct such a tree, so this visualisation'
                        + ' is necessarily very simplified.</p>';
                    extra += `<div id="receiptviz" class="receipt"><img src="${data.proof.ChainpointViz}" width="100%" /></div>`;
                }
            extra += '</div>';
            
            return extra;
        }
        
        /**
         * Generate the CMS' "dot dot dot" spinner markup.
         * There's probably some function somewhere to do this for us.
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
        
        /**
         * Returns the correct DOM node to use to attach messages and visual feedback
         * to the admin UI.
         * 
         * @return {DomNode}
         */
        function attachMessageTo() {
            return (function() {
                let isPage = $('#Root_Verify').length;
                let isFile = $('.gallery__main').length;
                
                if (isPage) {
                    return $('#Root_Verify');
                } else if (isFile) {
                    return $('.gallery__main');
                }
                
                return $('body');
            })();
        }
        
    });
})(jQuery);