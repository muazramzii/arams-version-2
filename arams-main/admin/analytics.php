<h2>Research Analytics</h2>

<canvas id="chart"></canvas>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

var ctx=document.getElementById('chart');

new Chart(ctx,{
type:'bar',
data:{
labels:['2021','2022','2023','2024'],
datasets:[{
label:'Publications',
data:[10,20,35,50]
}]
}
});

</script>