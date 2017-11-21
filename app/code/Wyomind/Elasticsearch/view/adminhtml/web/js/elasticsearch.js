require(["jquery", "Magento_Ui/js/modal/alert"], function ($, alert) {
    $(function () {
        jQuery(document).ready(function () {

            if (jQuery("#es_test_servers").length == 1) {
                jQuery("#es_test_servers").on('click', function () {

                    jQuery.ajax({
                        url: jQuery("#es_test_servers").attr("callback_url"),
                        data: {
                            servers : jQuery("#catalog_search_elasticsearch_servers").val()
                        },
                        type: 'POST',
                        showLoader: true,
                        success: function (data) {

                            var html = "";
                            
                            data.each(function(host_data) {
                                html += "<h3>"+host_data.host+"</h3>";
                                if (host_data.error != undefined) {
                                    html += "<span class='error'>ERROR</span><br/><br/>"+host_data.error;
                                } else {
                                    html += "<span class='success'>SUCCESS</span><br/><br/>";
                                    html += "<b>Name</b> : "+host_data.data.name+"<br/>";
                                    html += "<b>Cluster name</b> : "+host_data.data.cluster_name+"<br/>";
                                    html += "<b>Elasticsearch version</b> : "+host_data.data.version.number+"<br/>";
                                }
                                html += "<br/><br/>";
                            });

                            alert({
                                title: "",
                                content: html
                            });
                        }
                    });
                });
            }

        });
    });
});