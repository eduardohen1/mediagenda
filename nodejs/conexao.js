var mysql = require('mysql2');

var conexao = mysql.createConnection({
    host: 'localhost',
    port: 3307,
    user: 'root',
    password: '',
    database: 'labdbprog2'
});

conexao.connect(function(erro){
    if(erro){
        console.log('Erro ao conectar no banco de dados:');
        console.log(erro);
        return;
    }
    console.log('Conectado ao MySQL com sucesso!');
});
module.exports = conexao;