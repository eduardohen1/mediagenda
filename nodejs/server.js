const express = require('express');
const path = require('path');
var conexao = require('./conexao');
const cors = require('cors');

const app = express();
const porta = 3000;

/*app.arguments(express.json());
app.arguments(express.static(path.join(
    __dirname, 'public'
)));*/
app.use(express.json());
app.use(express.static(path.join(
    __dirname, 'public'
)));
app.use(cors());

const consultas = [
    {
        id: 1,
        paciente: 'Maria Souza',
        medico: 'Dr Fulano',
        especialidade: 'Cardiologia',
        data: '2026-05-12',
        horario: '09:00',
        status: 'confirmado'
    },
    {
        id: 2,
        paciente: 'Jao da Silva',
        medico: 'Dra Ciclana',
        especialidade: 'Dermatologia',
        data: '2026-05-12',
        horario: '10:30',
        status: 'pendente'
    },
    {
        id: 3,
        paciente: 'Ze Souza',
        medico: 'Dr Beltrano',
        especialidade: 'Ortopedia',
        data: '2026-05-13',
        horario: '14:00',
        status: 'confirmado'
    }
];

app.get('/api/consultas', function(req, res){
    for(i=0;i++;100000){
        x = i*1000;
        x = "";
        x=1111;
    }

    //res.json(consultas);
    var sql = "SELECT " +
                "id, paciente, medico, especialidade, data, horario, status " +
              "FROM vw_agendamentos " +
              "ORDER BY data,horario";
    conexao.query(sql, function(erro, resultados){
        if(erro){
            console.log('Erro ao buscar agendamentos:');
            console.log(erro);
            res.status(500).json({
                erro: true,
                mensagem: 'Erro ao buscar agendamentos'
            });
            return;
        }
        res.json(resultados);
    });
});

app.get('/api/status', function(req, res){
    res.json({
        sistema: 'MediAgenda Node',
        status: 'online',
        mensagem: 'Backend em NodeJS funcionando!'
    });
});

app.post('/api/agendamentos', function(req,res){
    var paciente         = req.body.paciente;
    var medico_id        = req.body.medico_id;
    var especialidade_id = req.body.especialidade_id;
    var data             = req.body.data;
    var horario          = req.body.horario;
    var status           = req.body.status;

    if(!paciente || !medico_id || !especialidade_id || !data || !horario || !status){
        res.status(400).json({
            erro: true,
            mensagem: 'Preencha todos os campos'
        });
        return;
    }
    var sql = "INSERT INTO agendamentos " +
               "(paciente, medico_id, especialidade_id, data, horario, status) " +
              "VALUES(?, ?, ?, ?, ?, ?)";
    var valores = [
        paciente, medico_id, especialidade_id, data, horario, status
    ];
    conexao.query(sql, valores, function(erro, resultado){
        if(erro){
            console.log('Erro ao cadastrar agendamento');
            console.log(erro);
            res.status(500).json({
                erro: true,
                mensagem: 'Erro ao cadastrar agendamento'
            });
            return;
        }
        res.status(201).json({
            erro: false,
            mensagem: 'Agendamento cadastrado com sucesso!',
            id: resultado.insertId
        });
    });

    /*
    Testar no terminal
    curl.exe -X POST "http://localhost:3000/api/agendamentos" -H "Content-type: application/json" -d "{\"paciente\":\"Jão Teste\",\"medico_id\":1,\"especialidade_id\":1,\"data\":\"2026-05-19\",\"horario\":\"09:45\",\"status\":\"Confirmado\"}"
    */

});

//listar apenas 1 agendamento
app.get('/api/agendamentos/:id', function(req, res){
    var id = req.params.id;
    var sql = "SELECT " +
                "id, paciente, medico, especialidade, data, horario, status " +
              "FROM vw_agendamentos " +
                "WHERE id = ?";
    conexao.query(sql, [id], function(erro, resultados){
        if(erro){
            console.log('Erro ao buscar agendamento:');
            console.log(erro);
            res.status(500).json({
                erro: true,
                mensagem: 'Erro ao buscar agendamento'
            });
            return;
        }
        if(resultados.length === 0){
            res.status(404).json({
                erro: true,
                mensagem: 'Agendamento não encontrado'
            });
            return;
        }
        res.json(resultados[0]);
    });
});

//atualizar um agendamento
app.put('/api/agendamentos/:id', function(req, res){
    var id               = req.params.id;
    var paciente         = req.body.paciente;
    var medico_id        = req.body.medico_id;
    var especialidade_id = req.body.especialidade_id;
    var data             = req.body.data;
    var horario          = req.body.horario;
    var status           = req.body.status;

    if(!paciente || !medico_id || !especialidade_id || !data || !horario || !status){
        res.status(400).json({
            erro: true,
            mensagem: 'Preencha todos os campos'
        });
        return;
    }
    var sql = "UPDATE agendamentos SET " +
              "paciente = ?, medico_id = ?, especialidade_id = ?, data = ?, horario = ?, status = ? " +
              "WHERE id = ?";
    var valores = [
        paciente, medico_id, especialidade_id, data, horario, status, id
    ];
    conexao.query(sql, valores, function(erro, resultado){
        if(erro){
            console.log('Erro ao atualizar agendamento');
            console.log(erro);
            res.status(500).json({
                erro: true,
                mensagem: 'Erro ao atualizar agendamento'
            });
            return;
        }
        res.json({
            erro: false,
            mensagem: 'Agendamento atualizado com sucesso!'
        });
    });
});

//deletar um agendamento
app.delete('/api/agendamentos/:id', function(req, res){
    var id = req.params.id;
    var sql = "DELETE FROM agendamentos WHERE id = ?";
    conexao.query(sql, [id], function(erro, resultado){
        if(erro){
            console.log('Erro ao deletar agendamento');
            console.log(erro);
            res.status(500).json({
                erro: true,
                mensagem: 'Erro ao deletar agendamento'
            });
            return;
        }
        res.json({
            erro: false,
            mensagem: 'Agendamento deletado com sucesso!'
        });
    });
});

//pesquisar agendamentos por filtros
app.get('/api/agendamentos/pesquisar/filtros', function(req, res){
    var sql = "SELECT " +
                "id, paciente, medico, especialidade, data, horario, status " +
                "FROM vw_agendamentos " +
                "WHERE id IN (SELECT id FROM agendamentos WHERE 1=1 ";
    var valores = [];
    if(req.query.paciente){
        sql += "AND paciente LIKE ? ";
        valores.push('%' + req.query.paciente + '%');
    }
    if(req.query.medico_id){
        sql += "AND medico_id = ? ";
        valores.push(req.query.medico_id);
    }
    if(req.query.especialidade_id){
        sql += "AND especialidade_id = ? ";
        valores.push(req.query.especialidade_id);
    }
    if(req.query.data_inicio && req.query.data_fim){
        sql += "AND data BETWEEN ? AND ? ";
        valores.push(req.query.data_inicio);
        valores.push(req.query.data_fim);
    }
    if(req.query.data_inicio && !req.query.data_fim){
        sql += "AND data >= ? ";
        valores.push(req.query.data_inicio);
    }
    if(!req.query.data_inicio && req.query.data_fim){
        sql += "AND data <= ? ";
        valores.push(req.query.data_fim);
    }
    sql += ") ORDER BY data, horario";
    console.log('SQL: ' + sql);

    conexao.query(sql, valores, function(erro, resultados){
        if(erro){
            console.log('Erro ao pesquisar agendamentos:');
            console.log(erro);
            res.status(500).json({
                erro: true,
                mensagem: 'Erro ao pesquisar agendamentos'
            });
            return;
        }
        res.json(resultados);
    });
});

app.listen(porta, function(){
    console.log('Servidor rodando em http://localhost:' + porta);
});