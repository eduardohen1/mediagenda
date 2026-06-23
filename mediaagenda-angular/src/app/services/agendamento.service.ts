import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class AgendamentoService {

  private apiURL = 'http://localhost:3000/api/agendamentos';

  constructor(private http: HttpClient) { }

  listar(): Observable<any[]>{
    return this.http.get<any[]>(this.apiURL);
  }
}
