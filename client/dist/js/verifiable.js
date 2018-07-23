/**
 * Simple entwine-backed JS logic for verifiable's GUI.
 * 
 * @package silverstripe-verifiable
 * @author Russell Michell 2018 <russ@theruss.com>
 */

// The Merkle-Tree aware backend that alters what the UI looks like and the fields
// we pick out of the module's XHR responses.
const backend = 'btc';
let btcTxid = '';

(function($) {
    $.entwine('ss.tree', function($) {
        $('.cms-edit-form').entwine({
            
            onmatch: function() {
                let version = $('select[name="Version"] option:selected').val();
                let $root = $('#Root_Verify');
                let urlArray = $('#tab-Root_Verify').attr('href').split('#')[0].split('/');
                let type = $('input[name="Type"]').val();                
                let id = urlArray[urlArray.length -1];
                
                $('select[name="Version"]').entwine({
                    onchange: function() {
                        version = $(this).val();
                    }
                });
                
                $('button[name="action_doVerify"]').on('click', function() {                    
                    let $button = $(this);
                    let controllerUrl = 'verifiable/verifyhash/' + type + '/' + id + '/' + version;
                    
                    $button
                        .addClass('btn--loading loading')
                        .append(spinner());
                
                    $('.v-status').remove();
                    
                    $.get(controllerUrl)
                        .done(function(data) {
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
                                
                            $root.prepend($('<p class="message v-status ' + msgCssClass + '">' + message + '</p>'));
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
                            $root.prepend($(msg));
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
                $('.extra-data').toggleClass('hide');
                
                doChainpointParse();
                
                let b = backendDisplay();
                let prf = JSON.parse($('#receipt').text());
                let res = chainpointParse.parse(prf);
                let btcTxid = res.branches[0].branches[0].btcTxId;
                let replacement = '';
                
                replacement += `<a title="View on ${b.explorer.name}"`;
                replacement += ` href="${b.explorer.base_url}/tx/${btcTxid}"`;
                replacement += ` target="_blank">${btcTxid}`;
                replacement += '<span class="font-icon-external-link">&nbsp;</span></a>';
                
                $('#lookuptxid').parent().html(replacement);
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
            
            extra += '<div class="extra-data hide">';
                extra += '<div class="message">';
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
                            extra += `<th>Created date</th><td>${data.record.CreatedDate}</td>`;
                        extra += '</tr><tr>';
                            extra += `<th>Local hash</th><td>${data.proof.Hashes.local}</td>`;
                        extra += '</tr><tr>';
                            extra += `<th>Remote hash</th><td>${data.proof.Hashes.remote}</td>`;
                        extra += '</tr><tr>';
                            extra += `<th>Submission date</th><td>${data.proof.SubmittedDate}</td>`; // BTC date
                        extra += '</tr><tr>';
                            extra += `<th>Merkle root</th><td>${data.proof.MerkleRoot}</td>`;
                        extra += '</tr><tr>';
                            extra += `<th>Block height</th><td>${data.proof.BlockHeight}</td>`;
                        extra += '</tr><tr>';
                            extra += `<th>Chainpoint identifier</th><td>${data.proof.UUID}</td>`;
                        extra += '</tr><tr>';
                            extra += `<th>Transaction</th><td><a href="#" id="lookuptxid">Lookup</a></td>`;
                        extra += '</tr>';
                    extra += '</table>';
                extra += '</div>';
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
            
            extra += '<div class="extra-data hide">';
                extra += '<div class="message">';
                    extra += '<p class="message notice"><strong>Chainpoint:</strong><br/>'
                        + ' A <a href="https://chainpoint.org/" target="_blank">ChainPoint</a> proof is:'
                        + ' "<strong>an open standard for creating a timestamp proof'
                        + ' of any data, file, or processs</strong>". Whenever a new'
                        + ' version is created, and we send its hash representation to the'
                        + ' 3rd party immutible storage, we receive a chainpoint "receipt" back'
                        + ' which contains all the meta-data that was used to construct'
                        + ' the "Root Hash" that is ultimately stored in the immutible storage itself.</p>';
                    extra += `<div id="receipt" class="receipt">${data.proof.ChainpointProof}</div>`;
                extra += '</div>';
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
         * Utilise the chainpoint-parse lib to take the local content hash, and
         * produce the chain of hashes required to arrive at a TXID, as well as the
         * merkle root and the TXID itself.
         * 
         * @return {void}
         */
        function doChainpointParse()
        {
            let b = backendDisplay();
            
            try {
                let prf = JSON.parse($('#receipt').text());
                let res = chainpointParse.parse(prf);
                let btc = {
                    txid: res.branches[0].branches[0].btcTxId,
                    hash: $('#local-hash').val(), // Take this value from an <input> field so users can futz with it
                    oprt: res.branches[0].branches[0].opReturnValue
                };
                
                // Update global var
                btcTxid = res.branches[0].branches[0].btcTxId;
            } catch (error) {
                $('.message.v-status').after(''
                    + '<p class="message bad manual"><strong>Error</strong> Something went'
                    + ' wrong with the hash calculation. <span class="hide">'
                    + error.message + '</span></p>'
                );
                console.error(error.message);
            }
        }
        
        /**
         * Basic marshall for displaying the correct UI component or text, based
         * on the currently configured backend.
         * 
         * @return {Object}
         */
        function backendDisplay()
        {
            if (backend === 'btc') {
                return {
                  'explorer': {'name': 'The Bitcoin Explorer', 'base_url': 'https://blockchain.info'}
                };
            } else {
                // NOOP
                return {};
            }
        }
        
    });
})(jQuery);