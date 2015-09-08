Iznik.Collections.DateCounts = IznikCollection.extend({});

Iznik.Views.MessageGraph = IznikView.extend({
    template: 'utils_message_graph',

    render: function() {
        var self = this;

        // Defer so that it's in the DOM - google stuff doesn't work well otherwise.
        _.defer(function() {
            self.$el.html(window.template(self.template)());

            var data = new google.visualization.DataTable();
            data.addColumn('date', 'Date');
            data.addColumn('number', 'Messages');
            self.options.data.each(function(count){
                // Don't show today's value, because it may be very low early in the day, which
                // will confuse people.
                if (self.options.data.indexOf(count) < self.options.data.length - 1) {
                    data.addRow([new Date(count.get('date')), parseInt(count.get('count'), 10)]);
                }
            });

            var formatter = new google.visualization.DateFormat({formatType: 'yy-M-d H'});
            formatter.format(data, 1);

            console.log("Target", self.options.target, data);

            self.chart = new google.visualization.LineChart(self.options.target);
            self.data = data;
            self.chartOptions = {
                title: self.options.title,
                interpolateNulls: false,
                legend: {position: 'none'},
                chartArea: {'width': '80%', 'height': '80%'},
                hAxis: {
                    format: 'dd MMM'
                },
                series: {
                    0: {color: 'blue'}
                }
            };
            self.chart.draw(self.data, self.chartOptions);
        });
    }
});

Iznik.Views.DomainChart = IznikView.extend({
    template: 'utils_domain_chart',

    render: function() {
        var self = this;

        // Defer so that it's in the DOM - google stuff doesn't work well otherwise.
        _.defer(function() {
            self.$el.html(window.template(self.template)());
            var arr = [['Domain', 'Count']];

            self.options.data.each(function(count) {
                arr.push([count.get('domain'), count.get('count')]);
            });
            console.log("Domain arr", arr);

            self.data = google.visualization.arrayToDataTable(arr);
            self.chart = new google.visualization.PieChart(self.options.target);
            self.chartOptions = {
                title: self.options.title,
                legend: {position: 'none'},
                chartArea: {'width': '80%', 'height': '80%'},
                slices2: {
                    1: {offset: 0.2},
                    2: {offset: 0.2}
                }
            };
            self.chart.draw(self.data, self.chartOptions);
        });
    }
});
