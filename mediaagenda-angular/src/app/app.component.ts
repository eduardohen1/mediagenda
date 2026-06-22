import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';

import Swal from 'sweetalert2';

interface Agendamento {
  id: number;
  paciente: string;
  medico: string;
  especialidade: string;
  data: string;
  horario: string;
  status: string;
}

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.css']
})
export class AppComponent implements OnInit {
  title = 'MediAgenda Angular';

  agendamentos: Agendamento[] = [];
  carregando = false;
  erro = '';

  constructor(private http: HttpClient) {}

  ngOnInit(){
    this.carregarAgendamentos();
  }

  carregarAgendamentos(){
    this.carregando = true;
    this.erro = '';

    this.http.get<Agendamento[]>('http://localhost:3000/api/consultas')
      .subscribe(
        dados => {
          this.agendamentos = dados;
          this.carregando = false;

          Swal.fire({
            icon: 'success',
            title: 'Agendamentos carregados!',
            text: 'Os dados foram buscados da API NodeJS',
            timer: 1800,
            showConfirmButton: false
          });
        },
        erro => {
          this.erro = 'Não foi possível carregar os agendamentos';
          this.carregando = false;
          Swal.fire({
            icon: 'error',
            title: 'Erro na API',
            text: 'Verifique o Backend!'            
          });
        }
      );
  }

  totalAgendamentos(){
    return this.agendamentos.length;
  }

  totalSituacao(situacao){
    var total = 0;
    switch(situacao){
      case 0:
        total = this.agendamentos.filter(
          a => a.status.toUpperCase() === 'CONFIRMADO'
        ).length;
        break;
      case 1:
        total = this.agendamentos.filter(
          a => a.status.toUpperCase() === 'PENDENTE'
        ).length;
        break;
      case 2:
        total = this.agendamentos.filter(
          a => a.status.toUpperCase() === 'CANCELADO'
        ).length;
        break;
    }
    return total;    
  }

  exibirDetalhes(agendamento: Agendamento){
    Swal.fire({
      title: 'Detalhes do Agendamento',
      html: 
        '<strong>Paciente:</strong>' + agendamento.paciente + '<br>' +
        '<strong>Médico:</strong>' + agendamento.medico + '<br>' +
        '<strong>Especialidade:</strong>' + agendamento.especialidade + '<br>' +
        '<strong>Data:</strong>' + agendamento.data + '<br>' +
        '<strong>Horário:</strong>' + agendamento.horario + '<br>' +
        '<strong>Status:</strong>' + agendamento.status,
      icon: 'info',
      confirmButtonText: 'Fechar'
    });
  }


}
