import React, {Component} from 'react';
import {connect} from 'react-redux';
import logo from '../logo.svg';
import {logout} from '../actions/Application';
import {Button, LinkButton} from '../components/Button'

class Root extends Component {

  logoutClick() {
    this.props.logout();
    return false;
  }

  render() {
    let loginButton, logoutButton;
    if (this.props.isAuthenticated) {
      logoutButton = <Button label="Logout" onClick={this.logoutClick.bind(this)}/>
    } else {
      loginButton = <LinkButton label="Login" to="login"/>
    }

    return (
      <div className="App">
        <div className="App-header">
          <img src={logo} className="App-logo" alt="logo"/>
          <h2>Welcome to OpenEx {this.props.user_firstname}</h2>
        </div>
        <div>
          <LinkButton label="Index" to="/"/>
          <LinkButton label="Home" to="home"/>
          { loginButton }
          { logoutButton }
        </div>
        <div>
          {this.props.children}
        </div>
      </div>
    )
  }
}

const select = (state, ownProps) => {
  var app = state.application;
  const isAuthenticated = app.hasIn(['token', 'token_id']) || false
  var user_firstname = app.getIn(['user', 'user_firstname']);
  return {
    isAuthenticated,
    user_firstname
  }
}

export default connect(select, {logout})(Root);