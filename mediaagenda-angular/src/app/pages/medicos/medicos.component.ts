import { Component, OnInit } from '@angular/core';
import { MedicoService } from '../../services/medico.service';

declare var Swal: any;

@Component({
  selector: 'app-medicos',
  templateUrl: './medicos.component.html',
  styleUrls: ['./medicos.component.css']
})
export class MedicosComponent implements OnInit {

  medicos: any[] = [];

  medico: any = {
    id: null,
    nome: '',
    crm: '',
    especialidadeId: null,
    email: ''
  };

  constructor(private service: MedicoService) { }

  ngOnInit() {
    this.listar();
  }

  listar(){
    this.service.listar().subscribe(
      dados => {
        this.medicos = dados;
      },
      erro => {
        Swal.fire('Erro', 'Erro ao carregar médicos da API Java', 'error');
      }
    );
  }

}
