import { Component, OnInit } from '@angular/core';
import { AgendamentoService } from '../../services/agendamento.service';

@Component({
  selector: 'app-agendamentos',
  templateUrl: './agendamentos.component.html',
  styleUrls: ['./agendamentos.component.css']
})
export class AgendamentosComponent implements OnInit {

  agendamentos: any[] = [];
  carregando = false;

  constructor(private service: AgendamentoService) { }

  ngOnInit() {
    this.listar();
  }

  listar(){
    this.carregando = true;
    this.service.listar().subscribe(
      dados => {
        this.agendamentos = dados;
        this.carregando = false;
      },
      erro => {
        this.carregando = false;
        alert('Erro ao carregar agendamentos do Node');
      }
    );
  }
}
