var express = require("express");
const app = express();

app.use(express.static("app"));

const http = require('http').createServer(app);

app.get('/', function (req, res) {
    res.send('<h1>Hello world</h1>');
});

http.listen(3000, function () {
    console.log('listening on *:3000');
});