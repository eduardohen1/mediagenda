import { NgModule } from '@angular/core';
import { Routes, RouterModule } from '@angular/router';

import { AgendamentosComponent } from './pages/agendamentos/agendamentos.component';
import { MedicosComponent } from './pages/medicos/medicos.component';


const routes: Routes = [
  {path: '', redirectTo: 'agendamentos', pathMatch: 'full'}, //http://localhost:3200/
  {path: 'agendamentos', component: AgendamentosComponent }, //http://localhost:3200/agendamentos
  {path: 'medicos', component: MedicosComponent }
];

@NgModule({
  imports: [RouterModule.forRoot(routes)],
  exports: [RouterModule]
})
export class AppRoutingModule { }
